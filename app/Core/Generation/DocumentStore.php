<?php

namespace App\Core\Generation;

use App\Core\Claude\ClaudeDocumentClient;
use App\Core\Claude\GenerateResult;
use App\Core\Modules\JobStep;
use App\Core\Modules\ModuleContract;
use App\Core\Validation\CheckResult;
use App\Core\Validation\ReportParser;
use App\Core\Validation\ValidationContext;
use App\Core\Validation\Validators\DocxIntegrityValidator;
use App\Models\GeneratedDocument;
use App\Models\GenerationTask;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengunduh berkas hasil dari Files API, menyimpannya sebagai versi baru,
 * lalu menjalankan pemeriksaan lapis 1 (laporan Claude) dan lapis 2 (mandiri).
 */
class DocumentStore
{
    public function __construct(private readonly ClaudeDocumentClient $client) {}

    public function storeFromResult(GenerationTask $task, ModuleContract $module, JobStep $step, GenerateResult $result): GeneratedDocument
    {
        $session = $task->session;
        $item = $task->item;
        $type = $module->documentTypes()[$step->docType] ?? null;
        $format = $type?->format ?? 'docx';

        $previous = GeneratedDocument::query()
            ->where('generation_session_id', $session->id)
            ->where('generation_item_id', $item?->id)
            ->where('doc_type', $step->docType)
            ->orderByDesc('version')
            ->first();

        $doc = new GeneratedDocument([
            'generation_session_id' => $session->id,
            'generation_item_id' => $item?->id,
            'generation_task_id' => $task->id,
            'user_id' => $session->user_id,
            'prodi_id' => $session->prodi_id,
            'module_key' => $module->key(),
            'doc_type' => $step->docType,
            'format' => $format,
            'version' => ($previous?->version ?? 0) + 1,
            'parent_id' => $previous?->id,
            'ref_code' => $item?->ref_code,
            'instruction' => $step->meta['instruction'] ?? null,
            'autofix_count' => (int) ($step->meta['autofix_count'] ?? 0),
            'model' => $task->model,
            'input_tokens' => $result->inputTokens(),
            'output_tokens' => $result->outputTokens(),
        ]);

        $report = ReportParser::parse($result->text);
        $doc->report = $report;

        $file = $this->pickOutputFile($result->outputFiles, $step->outputFilename, $format);
        if (! $file) {
            $doc->status = GeneratedDocument::STATUS_FAILED;
            $doc->filename = $step->outputFilename;
            $doc->checks = [CheckResult::fail('berkas_ada', 'Berkas hasil', 'Claude tidak menghasilkan berkas '.strtoupper($format).' pada langkah ini.')->toArray()];
            $doc->save();

            return $doc;
        }

        $filename = $step->outputFilename ?: ($file['filename'] ?? Str::slug($step->docType).'.'.$format);
        $path = sprintf('generated/%d/%s/%s/v%d-%s', $session->id, $step->docType, $item ? 'item-'.$item->id : 'sesi', $doc->version, $filename);

        try {
            $bytes = $this->client->downloadFile($file['file_id']);
        } catch (Throwable $e) {
            $doc->status = GeneratedDocument::STATUS_FAILED;
            $doc->filename = $filename;
            $doc->anthropic_file_id = $file['file_id'];
            $doc->checks = [CheckResult::fail('berkas_ada', 'Berkas hasil', 'Gagal mengunduh berkas hasil: '.$e->getMessage())->toArray()];
            $doc->save();

            return $doc;
        }

        Storage::disk('local')->put($path, $bytes);
        $doc->fill([
            'path' => $path,
            'filename' => $filename,
            'size' => strlen($bytes),
            'anthropic_file_id' => $file['file_id'],
        ]);
        $doc->save();

        $this->validate($doc, $module);

        return $doc;
    }

    /** Jalankan ulang seluruh pemeriksaan untuk satu dokumen. */
    public function validate(GeneratedDocument $doc, ModuleContract $module): GeneratedDocument
    {
        $context = new ValidationContext($doc, $doc->session, $doc->item, $doc->report);

        $checks = ReportParser::checkAgainstSchema($doc->report, $module->reportSchema($doc->doc_type));
        $validators = $module->validators($doc->doc_type);
        if ($doc->format === 'docx') {
            array_unshift($validators, new DocxIntegrityValidator);
        }

        foreach ($validators as $validator) {
            try {
                $checks = array_merge($checks, $validator->validate($context));
            } catch (Throwable $e) {
                report($e);
                $checks[] = CheckResult::fail(class_basename($validator), 'Pemeriksaan otomatis', 'Pemeriksaan gagal dijalankan: '.$e->getMessage());
            }
        }

        $doc->checks = array_map(fn (CheckResult $c) => $c->toArray(), $checks);
        $doc->status = collect($checks)->every(fn (CheckResult $c) => $c->passed)
            ? GeneratedDocument::STATUS_OK
            : GeneratedDocument::STATUS_ATTENTION;
        $doc->save();

        return $doc;
    }

    /**
     * Pilih berkas keluaran: nama yang diminta bila ada, selain itu berkas
     * terakhir dengan ekstensi format dokumen.
     *
     * @param  array<int, array{file_id: string, filename: ?string}>  $files
     */
    private function pickOutputFile(array $files, ?string $wanted, string $format): ?array
    {
        if (! $files) {
            return null;
        }

        foreach ($files as &$f) {
            if (empty($f['filename'])) {
                try {
                    $f['filename'] = $this->client->fileMetadata($f['file_id'])['filename'];
                } catch (Throwable) {
                    $f['filename'] = null;
                }
            }
        }
        unset($f);

        if ($wanted) {
            foreach (array_reverse($files) as $f) {
                if ($f['filename'] && Str::lower($f['filename']) === Str::lower($wanted)) {
                    return $f;
                }
            }
        }

        foreach (array_reverse($files) as $f) {
            if ($f['filename'] && Str::endsWith(Str::lower($f['filename']), '.'.$format)) {
                return $f;
            }
        }

        return null;
    }
}
