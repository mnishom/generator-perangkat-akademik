<?php

namespace App\Core\Claude;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Lapisan tipis di atas Laravel HTTP Client untuk Messages API + code execution
 * + Agent Skills + Files API. Dipakai semua modul.
 *
 * API key hanya dibaca dari config (berasal dari .env) dan tidak pernah dicatat ke log.
 */
class ClaudeDocumentClient
{
    public function __construct(private readonly array $config = []) {}

    public static function fromConfig(): static
    {
        return new static(config('claude'));
    }

    // ---------------------------------------------------------------- Files API

    /** Unggah berkas ke Files API; mengembalikan file_id. */
    public function uploadFile(string $path, string $filename, string $mime): string
    {
        $response = $this->send(fn () => $this->http()
            ->attach('file', fopen($path, 'r'), $filename, ['Content-Type' => $mime])
            ->post('/v1/files'));

        return (string) $response->json('id');
    }

    public function deleteFile(string $fileId): void
    {
        $this->send(fn () => $this->http()->delete('/v1/files/'.rawurlencode($fileId)), allow404: true);
    }

    /** @return array{id: string, filename: ?string, mime_type: ?string, size_bytes: ?int} */
    public function fileMetadata(string $fileId): array
    {
        $json = $this->send(fn () => $this->http()->get('/v1/files/'.rawurlencode($fileId)))->json();

        return [
            'id' => $json['id'] ?? $fileId,
            'filename' => $json['filename'] ?? null,
            'mime_type' => $json['mime_type'] ?? null,
            'size_bytes' => $json['size_bytes'] ?? null,
        ];
    }

    /** Unduh isi berkas hasil (hanya berkas buatan code execution/skills yang dapat diunduh). */
    public function downloadFile(string $fileId): string
    {
        return $this->send(fn () => $this->http()->get('/v1/files/'.rawurlencode($fileId).'/content'))->body();
    }

    // ------------------------------------------------------------- Messages API

    /**
     * Jalankan satu giliran generate sampai selesai. `pause_turn` dilanjutkan dengan
     * mengirim balik isi respons apa adanya; `max_tokens` dilanjutkan dengan pesan
     * lanjutan. Keduanya dibatasi `max_continuations`.
     */
    public function generate(GenerateRequest $request): GenerateResult
    {
        $messages = $request->messages;
        $containerId = $request->containerId;
        $expiresAt = null;
        $usage = ['input_tokens' => 0, 'output_tokens' => 0, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0];
        $files = [];
        $texts = [];
        $requests = 0;
        $maxContinuations = (int) ($this->config['max_continuations'] ?? 8);

        while (true) {
            $requests++;
            $json = $this->send(fn () => $this->http()
                ->withHeaders($this->betaHeaders())
                ->post('/v1/messages', $this->payload($request, $messages, $containerId)))->json();

            foreach ($usage as $k => $v) {
                $usage[$k] = $v + (int) ($json['usage'][$k] ?? 0);
            }
            if (! empty($json['container']['id'])) {
                $containerId = $json['container']['id'];
                $expiresAt = isset($json['container']['expires_at']) ? CarbonImmutable::parse($json['container']['expires_at']) : $expiresAt;
            }

            $content = $json['content'] ?? [];
            $files = array_merge($files, self::extractOutputFiles($content));
            $texts[] = self::extractText($content);
            $stop = (string) ($json['stop_reason'] ?? 'end_turn');

            if ($stop === 'refusal') {
                $why = $json['stop_details']['explanation'] ?? ($json['stop_details']['category'] ?? '');
                throw new ClaudeException('Claude menolak permintaan ini'.($why ? ': '.$why : '.'), transient: false, errorType: 'refusal');
            }

            $messages = self::appendAssistant($messages, $content);

            if (! in_array($stop, ['pause_turn', 'max_tokens'], true) || $requests > $maxContinuations) {
                if (in_array($stop, ['pause_turn', 'max_tokens'], true)) {
                    throw new ClaudeException("Proses belum selesai setelah {$maxContinuations} kali pelanjutan.", transient: false, errorType: 'too_many_continuations');
                }
                break;
            }

            if ($stop === 'max_tokens') {
                $messages[] = ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Lanjutkan tepat dari titik terakhir tanpa mengulang bagian yang sudah ditulis.']]];
            }
            // pause_turn: kirim ulang percakapan apa adanya; server melanjutkan otomatis.
        }

        return new GenerateResult(
            messages: $messages,
            text: trim(implode("\n", array_filter($texts))),
            stopReason: $stop,
            containerId: $containerId,
            containerExpiresAt: $expiresAt,
            outputFiles: self::uniqueFiles($files),
            usage: $usage,
            requests: $requests,
        );
    }

    public function payload(GenerateRequest $request, array $messages, ?string $containerId): array
    {
        $container = ['skills' => array_map(fn ($s) => ['type' => 'anthropic', 'skill_id' => $s, 'version' => 'latest'], array_values(array_unique($request->skills)))];
        if ($containerId) {
            $container['id'] = $containerId;
        }

        $payload = [
            'model' => $request->model,
            'max_tokens' => $request->maxTokens ?? (int) ($this->config['max_tokens'] ?? 32000),
            'messages' => $messages,
            'tools' => [['type' => $this->config['code_execution_tool'] ?? 'code_execution_20260521', 'name' => 'code_execution']],
            'container' => $container,
        ];
        if ($request->system !== '') {
            $payload['system'] = $request->system;
        }
        if ($effort = $request->effort ?? ($this->config['effort'] ?? null)) {
            $payload['output_config'] = ['effort' => $effort];
        }
        if ($this->config['auto_cache'] ?? true) {
            $payload['cache_control'] = ['type' => 'ephemeral'];
        }
        if ($fallbacks = $this->config['fallbacks'] ?? null) {
            $payload['fallbacks'] = $fallbacks;
        }

        return $payload;
    }

    // ---------------------------------------------------------------- Helpers

    /** Blok `container_upload` untuk setiap file_id masukan. */
    public static function containerUploads(array $fileIds): array
    {
        return array_values(array_map(fn ($id) => ['type' => 'container_upload', 'file_id' => $id], $fileIds));
    }

    /** @return array<int, array{file_id: string, filename: ?string}> */
    public static function extractOutputFiles(array $content): array
    {
        $files = [];
        foreach ($content as $block) {
            $type = $block['type'] ?? '';
            if (! str_ends_with($type, 'code_execution_tool_result')) {
                continue;
            }
            foreach (($block['content']['content'] ?? []) as $out) {
                if (! empty($out['file_id'])) {
                    $files[] = ['file_id' => $out['file_id'], 'filename' => $out['filename'] ?? null];
                }
            }
        }

        return $files;
    }

    public static function extractText(array $content): string
    {
        return trim(implode("\n", array_map(
            fn ($b) => $b['text'] ?? '',
            array_filter($content, fn ($b) => ($b['type'] ?? '') === 'text')
        )));
    }

    private static function appendAssistant(array $messages, array $content): array
    {
        $last = end($messages);
        if ($last && ($last['role'] ?? '') === 'assistant') {
            // Lanjutan pause_turn: gabungkan ke giliran assistant yang sama.
            $messages[array_key_last($messages)]['content'] = array_merge($last['content'], $content);
        } else {
            $messages[] = ['role' => 'assistant', 'content' => $content];
        }

        return $messages;
    }

    private static function uniqueFiles(array $files): array
    {
        $seen = [];

        return array_values(array_filter($files, function ($f) use (&$seen) {
            if (isset($seen[$f['file_id']])) {
                return false;
            }

            return $seen[$f['file_id']] = true;
        }));
    }

    private function betaHeaders(): array
    {
        $betas = $this->config['betas'] ?? [];
        if (! empty($this->config['fallbacks'])) {
            $betas[] = $this->config['fallbacks_beta'] ?? 'server-side-fallback-2026-07-01';
        }

        return $betas ? ['anthropic-beta' => implode(',', array_unique($betas))] : [];
    }

    private function http(): PendingRequest
    {
        $key = (string) ($this->config['api_key'] ?? '');
        if ($key === '') {
            throw new ClaudeException('ANTHROPIC_API_KEY belum diatur di .env.', transient: false, errorType: 'config');
        }

        return Http::baseUrl(rtrim($this->config['base_url'] ?? 'https://api.anthropic.com', '/'))
            ->withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => $this->config['version'] ?? '2023-06-01',
            ])
            ->acceptJson()
            ->timeout((int) ($this->config['timeout'] ?? 600))
            ->connectTimeout(30);
    }

    /** Kirim permintaan dan ubah galat HTTP menjadi ClaudeException (tanpa membocorkan header). */
    private function send(callable $call, bool $allow404 = false): Response
    {
        try {
            /** @var Response $response */
            $response = $call();
        } catch (ConnectionException $e) {
            throw new ClaudeException('Koneksi ke Claude API gagal: '.$e->getMessage(), transient: true);
        }

        if ($response->successful() || ($allow404 && $response->status() === 404)) {
            return $response;
        }

        $status = $response->status();
        $type = $response->json('error.type');
        $message = $response->json('error.message') ?: 'HTTP '.$status;
        $transient = $status === 429 || $status === 408 || $status >= 500;
        $retryAfter = $response->header('retry-after') !== '' ? (int) $response->header('retry-after') : null;

        throw new ClaudeException("Claude API {$status}".($type ? " ({$type})" : '').': '.$message, $transient, $status, $type, $retryAfter);
    }
}
