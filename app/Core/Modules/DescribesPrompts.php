<?php

namespace App\Core\Modules;

use App\Models\GenerationSession;

/**
 * Opsional untuk modul: keterangan prompt dan variabelnya (untuk editor prompt,
 * panel variabel, dan peringatan variabel penting yang hilang).
 */
interface DescribesPrompts
{
    /**
     * Prompt yang relevan untuk sesi (null = semua prompt modul).
     *
     * @return array<string, array{label: string, description?: string, variables: array<string, array{description: string, required?: bool}>}>
     */
    public function promptDefinitions(?GenerationSession $session = null): array;
}
