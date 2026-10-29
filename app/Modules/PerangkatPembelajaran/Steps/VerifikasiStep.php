<?php

namespace App\Modules\PerangkatPembelajaran\Steps;

use App\Core\Curriculum\CurriculumExtractor;
use App\Core\Wizard\WizardStep;
use App\Models\CurriculumExtraction;
use App\Models\GenerationSession;
use App\Models\LibraryFile;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use RuntimeException;

class VerifikasiStep extends WizardStep
{
    public array $selectedCodes = [];

    public bool $generateSilabus = false;

    public function mount(): void
    {
        $session = $this->session();
        $this->generateSilabus = (bool) $session->option('generate_silabus', ! empty($session->inputs['template_silabus']));
        $items = $session->items;
        $this->selectedCodes = $items->isNotEmpty()
            ? $items->where('selected', true)->pluck('ref_code')->values()->all()
            : array_values(array_filter(array_column($this->extraction()?->courses() ?? [], 'kode')));
    }

    private function extraction(): ?CurriculumExtraction
    {
        $id = $this->session()->curriculum_extraction_id;

        return $id ? CurriculumExtraction::find($id) : null;
    }

    private function curriculumFile(): ?LibraryFile
    {
        $id = $this->session()->inputs['kurikulum'] ?? null;

        return $id ? LibraryFile::withTrashed()->find($id) : null;
    }

    public function startExtraction(CurriculumExtractor $extractor): void
    {
        $session = $this->session();
        $file = $this->curriculumFile();
        abort_unless($file, 422);

        try {
            $extraction = $extractor->start($file, auth()->user(), $session->module_key);
        } catch (RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }
        $session->update(['curriculum_extraction_id' => $extraction->id]);
        $this->toast('Ekstraksi dimulai. Proses ini dapat memakan beberapa menit; halaman diperbarui otomatis.', 'info');
    }

    public function useReusable(int $id, CurriculumExtractor $extractor): void
    {
        $file = $this->curriculumFile();
        $reusable = $file ? $extractor->findReusable($file, auth()->user()->prodi_id) : null;
        abort_unless($reusable && $reusable->id === $id, 404);

        $this->session()->update(['curriculum_extraction_id' => $reusable->id]);
        $this->selectedCodes = array_values(array_filter(array_column($reusable->courses(), 'kode')));
        $this->toast('Memakai data kurikulum terverifikasi sebelumnya.');
    }

    #[On('curriculum-verified')]
    public function verified(): void
    {
        $codes = array_column($this->extraction()?->courses() ?? [], 'kode');
        $this->selectedCodes = array_values(array_intersect($this->selectedCodes, $codes)) ?: $codes;
    }

    public function continue(): void
    {
        $session = $this->session();
        $extraction = $this->extraction();

        if (! $extraction?->isVerified()) {
            $this->addError('verify', 'Periksa lalu tekan "Simpan & verifikasi data" terlebih dahulu.');

            return;
        }

        $hasRps = ! empty($session->inputs['template_rps']);
        $hasSilabus = ! empty($session->inputs['template_silabus']);
        $silabus = $hasSilabus && $this->generateSilabus;
        if ((! $hasRps || ! $this->selectedCodes) && ! $silabus) {
            $this->addError('verify', $hasRps ? 'Pilih minimal satu MK, atau aktifkan Silabus Prodi.' : 'Aktifkan Silabus Prodi (Template RPS tidak tersedia).');

            return;
        }

        $this->syncItems($session, $extraction, $hasRps ? $this->selectedCodes : []);
        $session->options = array_merge($session->options ?? [], ['generate_silabus' => $silabus]);
        $session->save();

        $this->next();
    }

    /** Butir sesi = seluruh MK terverifikasi; `selected` menandai MK yang dibuatkan RPS & Kontrak. */
    private function syncItems(GenerationSession $session, CurriculumExtraction $extraction, array $selected): void
    {
        DB::transaction(function () use ($session, $extraction, $selected) {
            $existing = $session->items()->get()->keyBy('ref_code');
            $codes = [];
            foreach ($extraction->courses() as $i => $mk) {
                if (! $mk['kode']) {
                    continue;
                }
                $codes[] = $mk['kode'];
                $item = $existing[$mk['kode']] ?? $session->items()->make(['ref_code' => $mk['kode']]);
                $item->fill([
                    'ref_label' => $mk['nama'],
                    'input' => $mk,
                    'selected' => in_array($mk['kode'], $selected, true),
                    'sort' => $i,
                ])->save();
            }
            $session->items()->whereNotIn('ref_code', $codes)->doesntHave('documents')->delete();
            $session->items()->whereNotIn('ref_code', $codes)->update(['selected' => false]);
        });
    }

    public function back(): void
    {
        $this->dispatch('wizard-back');
    }

    public function render(CurriculumExtractor $extractor)
    {
        $session = $this->session();
        $extraction = $this->extraction();
        $file = $this->curriculumFile();

        return view('modules.perangkat-pembelajaran.verifikasi-step', [
            'extraction' => $extraction,
            'file' => $file,
            'reusable' => ! $extraction && $file ? $extractor->findReusable($file, auth()->user()->prodi_id) : null,
            'hasRps' => ! empty($session->inputs['template_rps']),
            'hasSilabus' => ! empty($session->inputs['template_silabus']),
            'hasKontrak' => ! empty($session->inputs['template_kontrak']),
        ]);
    }
}
