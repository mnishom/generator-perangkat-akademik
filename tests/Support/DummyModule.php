<?php

namespace Tests\Support;

use App\Core\Modules\DocumentTypeDefinition;
use App\Core\Modules\InputRequirement;
use App\Core\Modules\JobPlan;
use App\Core\Modules\JobStep;
use App\Core\Modules\ModuleContract;
use App\Core\Validation\CheckResult;
use App\Core\Validation\ValidationContext;
use App\Core\Validation\ValidatorContract;
use App\Models\GenerationSession;

/**
 * Modul tiruan untuk menguji bahwa inti platform bekerja tanpa mengenal modul tertentu.
 * Satu Job per butir dengan dua langkah (catatan → ringkasan) + satu Job tingkat sesi.
 */
class DummyModule implements ModuleContract
{
    public function key(): string
    {
        return 'dummy';
    }

    public function name(): string
    {
        return 'Modul Tiruan';
    }

    public function description(): string
    {
        return 'Modul untuk pengujian.';
    }

    public function icon(): string
    {
        return 'book';
    }

    public function documentTypes(): array
    {
        return [
            'catatan' => new DocumentTypeDefinition('catatan', 'Catatan', filenamePattern: 'Catatan_{kode}.docx'),
            'ringkasan' => new DocumentTypeDefinition('ringkasan', 'Ringkasan', filenamePattern: 'Ringkasan_{kode}.docx'),
            'rekap' => new DocumentTypeDefinition('rekap', 'Rekap Sesi', scope: 'session'),
        ];
    }

    public function inputRequirements(): array
    {
        return [new InputRequirement('sumber', 'Dokumen sumber')];
    }

    public function wizardSteps(): array
    {
        return [];
    }

    public function defaultPrompts(): array
    {
        return [];
    }

    public function systemInstructions(string $docType): string
    {
        return "SISTEM {$docType}";
    }

    public function reportSchema(string $docType): array
    {
        return ['kode' => 'string'];
    }

    public function validators(string $docType): array
    {
        return [new class implements ValidatorContract
        {
            public function validate(ValidationContext $context): array
            {
                $kode = $context->itemInput()['kode'] ?? null;
                if (! $kode) {
                    return [];
                }

                return [str_contains($context->text(), $kode)
                    ? CheckResult::pass('kode', 'Kode tercantum')
                    : CheckResult::fail('kode', 'Kode tercantum', "Kode {$kode} tidak ada di dokumen.")];
            }
        }];
    }

    public function planJobs(GenerationSession $session): array
    {
        $plans = [];
        foreach ($session->items as $item) {
            $kode = $item->input['kode'];
            $plans[] = new JobPlan("item:{$kode}", "Butir {$kode}", [
                new JobStep('catatan', 'Catatan', "Buat catatan {$kode}", 'catatan', ['sumber'], "Catatan_{$kode}.docx", system: $this->systemInstructions('catatan')),
                new JobStep('ringkasan', 'Ringkasan', "Buat ringkasan {$kode}", 'ringkasan', ['sumber'], "Ringkasan_{$kode}.docx", system: $this->systemInstructions('ringkasan')),
            ], $item);
        }
        $plans[] = new JobPlan('sesi:rekap', 'Rekap', [
            new JobStep('rekap', 'Rekap', 'Buat rekap', 'rekap', ['sumber'], 'Rekap.docx'),
        ]);

        return $plans;
    }
}
