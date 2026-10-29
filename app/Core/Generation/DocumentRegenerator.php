<?php

namespace App\Core\Generation;

use App\Core\Modules\JobStep;
use App\Core\Modules\ModuleRegistry;
use App\Models\GeneratedDocument;
use App\Services\Settings;
use RuntimeException;

/**
 * Generate ulang satu dokumen (dengan instruksi tambahan) atau perbaikan otomatis
 * berdasarkan daftar masalah pemeriksaan. Berjalan di container dan percakapan
 * yang sama dengan pembuatannya, menghasilkan versi baru tanpa menghapus versi lama.
 */
class DocumentRegenerator
{
    public function __construct(
        private readonly SessionLauncher $launcher,
        private readonly ModuleRegistry $modules,
        private readonly Settings $settings,
    ) {}

    public function regenerate(GeneratedDocument $doc, string $instruction): void
    {
        $instruction = trim($instruction);
        if ($instruction === '') {
            throw new RuntimeException('Instruksi tambahan wajib diisi.');
        }

        $this->run($doc, "Generate ulang {$this->label($doc)} dengan instruksi tambahan berikut:\n{$instruction}", [
            'instruction' => $instruction,
            'autofix_count' => $doc->autofix_count,
        ]);
    }

    public function autofixAllowed(GeneratedDocument $doc): bool
    {
        return $doc->status !== GeneratedDocument::STATUS_OK
            && $doc->autofix_count < (int) $this->settings->get('autofix_max', 2);
    }

    public function autofix(GeneratedDocument $doc): void
    {
        if (! $this->autofixAllowed($doc)) {
            throw new RuntimeException('Batas perbaikan otomatis tercapai. Gunakan generate ulang dengan instruksi Anda sendiri.');
        }

        $problems = collect($doc->failedChecks())->map(fn ($c) => "- {$c['label']}: {$c['message']}")->implode("\n");
        $this->run($doc, "Perbaiki {$this->label($doc)} yang terakhir Anda buat. Pemeriksaan otomatis aplikasi menemukan masalah berikut:\n{$problems}\n\nPerbaiki semua masalah tersebut tanpa mengubah bagian lain yang sudah benar dan tanpa mengubah format template.", [
            'instruction' => 'Perbaikan otomatis: '.collect($doc->failedChecks())->pluck('label')->implode(', '),
            'autofix_count' => $doc->autofix_count + 1,
        ]);
    }

    private function run(GeneratedDocument $doc, string $request, array $meta): void
    {
        $task = $doc->task;
        if (! $task) {
            throw new RuntimeException('Proses asal dokumen ini tidak ditemukan.');
        }

        $module = $this->modules->get($doc->module_key);
        $schema = $module->reportSchema($doc->doc_type);
        $fields = implode("\n", array_map(fn ($k, $v) => "- `{$k}`: {$v}", array_keys($schema), $schema));
        $filename = $doc->filename ?: $doc->doc_type.'.'.$doc->format;
        $original = collect($task->jobSteps())->last(fn (JobStep $s) => $s->docType === $doc->doc_type);

        $prompt = $request."\n\nSimpan dokumen final sebagai `\$OUTPUT_DIR/{$filename}` (nama yang sama).".
            ($fields ? "\n\nAkhiri jawaban dengan satu blok ```json berisi laporan pemeriksaan dengan kolom:\n{$fields}" : '');

        $this->launcher->appendAndRun($task, new JobStep(
            key: $doc->doc_type.'_v'.($doc->version + 1),
            label: $this->label($doc).' (versi baru)',
            prompt: $prompt,
            docType: $doc->doc_type,
            inputRoles: $original?->inputRoles ?? [],
            outputFilename: $filename,
            system: $original?->system ?? '',
            meta: $meta,
        ));
    }

    private function label(GeneratedDocument $doc): string
    {
        $type = $this->modules->has($doc->module_key) ? ($this->modules->get($doc->module_key)->documentTypes()[$doc->doc_type] ?? null) : null;

        return ($type?->label ?? $doc->doc_type).($doc->ref_code ? " {$doc->ref_code}" : '');
    }
}
