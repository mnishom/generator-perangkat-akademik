<?php

use App\Core\Claude\ClaudeDocumentClient;
use App\Core\Claude\ClaudeException;
use App\Core\Claude\GenerateRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function client(array $over = []): ClaudeDocumentClient
{
    return new ClaudeDocumentClient(array_merge(config('claude'), ['api_key' => 'sk-test-rahasia', 'fallbacks' => null], $over));
}

function messageResponse(array $content, string $stop = 'end_turn', ?string $container = 'cntr_abc'): array
{
    return [
        'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'content' => $content, 'stop_reason' => $stop,
        'container' => $container ? ['id' => $container, 'expires_at' => '2026-11-28T00:00:00Z'] : null,
        'usage' => ['input_tokens' => 100, 'output_tokens' => 50, 'cache_read_input_tokens' => 10, 'cache_creation_input_tokens' => 5],
    ];
}

function bashResult(array $fileIds): array
{
    return ['type' => 'bash_code_execution_tool_result', 'tool_use_id' => 'srvtoolu_1', 'content' => [
        'type' => 'bash_code_execution_result', 'stdout' => '', 'stderr' => '', 'return_code' => 0,
        'content' => array_map(fn ($id) => ['type' => 'bash_code_execution_output', 'file_id' => $id], $fileIds),
    ]];
}

it('mengirim permintaan generate dengan skills, code execution, dan beta header yang benar', function () {
    Http::fake(['api.anthropic.com/v1/messages' => Http::response(messageResponse([['type' => 'text', 'text' => 'ok']]))]);

    $result = client()->generate(new GenerateRequest('claude-sonnet-5-5', [['role' => 'user', 'content' => 'hai']], 'SISTEM', ['docx', 'pdf']));

    Http::assertSent(function (Request $r) {
        $body = $r->data();

        return $r->url() === 'https://api.anthropic.com/v1/messages'
            && $r->hasHeader('x-api-key', 'sk-test-rahasia')
            && $r->hasHeader('anthropic-version', '2023-06-01')
            && $r->hasHeader('anthropic-beta', 'code-execution-2025-08-25')
            && $body['model'] === 'claude-sonnet-5-5'
            && $body['system'] === 'SISTEM'
            && $body['tools'] === [['type' => 'code_execution_20260521', 'name' => 'code_execution']]
            && $body['container'] === ['skills' => [
                ['type' => 'anthropic', 'skill_id' => 'docx', 'version' => 'latest'],
                ['type' => 'anthropic', 'skill_id' => 'pdf', 'version' => 'latest'],
            ]]
            && ! isset($body['container']['id']);
    });
    expect($result->containerId)->toBe('cntr_abc')
        ->and($result->containerExpiresAt?->toIso8601String())->toBe('2026-11-28T00:00:00+00:00')
        ->and($result->text)->toBe('ok')
        ->and($result->inputTokens())->toBe(115);
});

it('memakai ulang container yang diberikan', function () {
    Http::fake(['*' => Http::response(messageResponse([['type' => 'text', 'text' => 'ok']]))]);

    client()->generate(new GenerateRequest('claude-sonnet-5-5', [['role' => 'user', 'content' => 'x']], containerId: 'cntr_lama'));

    Http::assertSent(fn (Request $r) => $r->data()['container']['id'] === 'cntr_lama'
        && $r->data()['container']['skills'][0]['skill_id'] === 'docx');
});

it('melanjutkan pause_turn dengan mengirim balik isi respons tanpa pesan tambahan', function () {
    Http::fakeSequence()
        ->push(messageResponse([['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'bash_code_execution', 'input' => ['command' => 'ls']]], 'pause_turn'))
        ->push(messageResponse([bashResult(['file_out_1']), ['type' => 'text', 'text' => "selesai\n```json\n{\"a\":1}\n```"]]));

    $result = client()->generate(new GenerateRequest('claude-sonnet-5-5', [['role' => 'user', 'content' => 'buat']]));

    $sent = Http::recorded()->map(fn ($p) => $p[0]->data());
    expect($sent)->toHaveCount(2)
        ->and($sent[1]['messages'])->toHaveCount(2)
        ->and($sent[1]['messages'][1]['role'])->toBe('assistant')
        ->and($sent[1]['messages'][1]['content'][0]['type'])->toBe('server_tool_use')
        ->and($sent[1]['container']['id'])->toBe('cntr_abc');
    expect($result->outputFiles)->toBe([['file_id' => 'file_out_1', 'filename' => null]])
        ->and($result->requests)->toBe(2)
        ->and($result->usage['input_tokens'])->toBe(200)
        ->and($result->stopReason)->toBe('end_turn');
});

it('berhenti setelah batas pelanjutan', function () {
    Http::fake(['*' => Http::response(messageResponse([['type' => 'text', 'text' => '...']], 'pause_turn'))]);

    client(['max_continuations' => 2])->generate(new GenerateRequest('claude-sonnet-5-5', [['role' => 'user', 'content' => 'x']]));
})->throws(ClaudeException::class, 'pelanjutan');

it('memetakan galat HTTP ke ClaudeException sementara atau permanen', function (int $status, bool $transient) {
    Http::fake(['*' => Http::response(['type' => 'error', 'error' => ['type' => 'x_error', 'message' => 'pesan']], $status, ['retry-after' => '7'])]);

    try {
        client()->generate(new GenerateRequest('claude-sonnet-5-5', [['role' => 'user', 'content' => 'x']]));
        $this->fail('tidak melempar');
    } catch (ClaudeException $e) {
        expect($e->transient)->toBe($transient)->and($e->status)->toBe($status)->and($e->getMessage())->toContain('pesan');
        if ($status === 429) {
            expect($e->retryAfter)->toBe(7);
        }
    }
})->with([[429, true], [500, true], [529, true], [400, false], [401, false]]);

it('menganggap refusal sebagai galat permanen', function () {
    Http::fake(['*' => Http::response(array_merge(messageResponse([]), ['stop_reason' => 'refusal', 'stop_details' => ['type' => 'refusal', 'category' => 'cyber', 'explanation' => 'tidak boleh']]))]);

    client()->generate(new GenerateRequest('claude-sonnet-5-5', [['role' => 'user', 'content' => 'x']]));
})->throws(ClaudeException::class, 'menolak');

it('mengunggah, membaca metadata, mengunduh, dan menghapus berkas lewat Files API', function () {
    Http::fake([
        'api.anthropic.com/v1/files' => Http::response(['id' => 'file_up_9', 'filename' => 'k.docx']),
        'api.anthropic.com/v1/files/file_out_1/content' => Http::response('ISI-DOCX'),
        'api.anthropic.com/v1/files/file_out_1' => Http::response(['id' => 'file_out_1', 'filename' => 'RPS_X.docx', 'size_bytes' => 8]),
        'api.anthropic.com/v1/files/file_up_9' => Http::response(['id' => 'file_up_9', 'deleted' => true]),
    ]);
    $tmp = tempnam(sys_get_temp_dir(), 'f');
    file_put_contents($tmp, 'x');

    $c = client();
    expect($c->uploadFile($tmp, 'k.docx', 'application/pdf'))->toBe('file_up_9')
        ->and($c->fileMetadata('file_out_1')['filename'])->toBe('RPS_X.docx')
        ->and($c->downloadFile('file_out_1'))->toBe('ISI-DOCX');
    $c->deleteFile('file_up_9');

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === 'https://api.anthropic.com/v1/files' && $r->isMultipart());
    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/v1/files/file_up_9'));
});

it('menambahkan header fallback bila diaktifkan', function () {
    Http::fake(['*' => Http::response(messageResponse([['type' => 'text', 'text' => 'ok']]))]);

    client(['fallbacks' => 'default'])->generate(new GenerateRequest('claude-sonnet-5-5', [['role' => 'user', 'content' => 'x']]));

    Http::assertSent(fn (Request $r) => $r->data()['fallbacks'] === 'default'
        && str_contains($r->header('anthropic-beta')[0], 'server-side-fallback-2026-07-01'));
});

it('menolak bila API key belum diatur dan tidak pernah menulis API key ke log', function () {
    Log::spy();
    Http::fake(['*' => Http::response(['error' => ['message' => 'bad']], 400)]);

    expect(fn () => client(['api_key' => ''])->generate(new GenerateRequest('m', [])))->toThrow(ClaudeException::class, 'ANTHROPIC_API_KEY');

    try {
        client()->generate(new GenerateRequest('m', []));
    } catch (ClaudeException $e) {
        expect($e->getMessage())->not->toContain('sk-test-rahasia');
    }
    Log::shouldNotHaveReceived('info');
});
