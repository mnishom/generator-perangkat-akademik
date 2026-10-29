<?php

namespace App\Core\Modules;

use App\Core\Validation\ValidatorContract;
use App\Models\GenerationSession;

/**
 * Kontrak yang wajib dipenuhi setiap modul di app/Modules/<NamaModul>/.
 *
 * Inti platform tidak mengenal jenis dokumen tertentu; semua yang khas
 * (langkah wizard, jenis dokumen, prompt, instruksi sistem, skema laporan,
 * pemeriksa mandiri, dan pembagian Job) berasal dari implementasi kontrak ini.
 */
interface ModuleContract
{
    /** Kunci unik, huruf kecil dan garis bawah, misalnya `perangkat_pembelajaran`. */
    public function key(): string;

    public function name(): string;

    public function description(): string;

    /** Nama ikon (lihat resources/views/components/icon.blade.php). */
    public function icon(): string;

    /** @return array<string, DocumentTypeDefinition> kunci jenis dokumen => definisi */
    public function documentTypes(): array;

    /** @return InputRequirement[] */
    public function inputRequirements(): array;

    /**
     * Langkah wizard berurutan. Setiap langkah adalah komponen Livewire yang
     * menerima parameter `sessionId` dan memanggil `$this->dispatch('wizard-next')`
     * setelah datanya tersimpan.
     *
     * @return array<string, array{label: string, component: class-string}>
     */
    public function wizardSteps(): array;

    /** @return array<string, string> kunci prompt => path berkas prompt default (.md) */
    public function defaultPrompts(): array;

    /** Instruksi sistem tetap (tidak dapat diedit dari UI) untuk satu jenis dokumen. */
    public function systemInstructions(string $docType): string;

    /** Skema laporan JSON yang wajib diakhiri Claude: nama kolom => tipe/keterangan. */
    public function reportSchema(string $docType): array;

    /** @return ValidatorContract[] pemeriksa mandiri (lapis 2) untuk satu jenis dokumen */
    public function validators(string $docType): array;

    /**
     * Memecah sesi menjadi rencana Job. Setiap JobPlan dijalankan sebagai satu
     * Job antrean dengan langkah-langkah berurutan di container yang sama.
     *
     * @return JobPlan[]
     */
    public function planJobs(GenerationSession $session): array;
}
