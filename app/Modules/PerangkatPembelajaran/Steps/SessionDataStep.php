<?php

namespace App\Modules\PerangkatPembelajaran\Steps;

use App\Core\Wizard\WizardStep;

class SessionDataStep extends WizardStep
{
    public array $form = [];

    public function mount(): void
    {
        $session = $this->session();
        $prodi = $session->prodi()->with('fakultas')->first();
        $identity = $session->identity ?? [];
        $hasIdentity = $session->identity !== null;

        $this->form = [
            'title' => $session->title ?? '',
            'tanggal_penyusunan' => ($session->tanggal_penyusunan ?? now())->format('Y-m-d'),
            'tahun_akademik' => $session->tahun_akademik ?? self::defaultAcademicYear(),
            // Bawaan dari data prodi/fakultas; pengguna boleh mengosongkan (tidak akan dikarang).
            'nama_fakultas' => $hasIdentity ? ($identity['nama_fakultas'] ?? '') : ($prodi?->fakultas?->nama ?? ''),
            'nama_prodi' => $hasIdentity ? ($identity['nama_prodi'] ?? '') : ($prodi?->namaLengkap() ?? ''),
            'nama_kaprodi' => $hasIdentity ? ($identity['nama_kaprodi'] ?? '') : ($prodi?->nama_kaprodi ?? ''),
            'nama_dekan' => $hasIdentity ? ($identity['nama_dekan'] ?? '') : ($prodi?->fakultas?->nama_dekan ?? ''),
        ];
    }

    public static function defaultAcademicYear(): string
    {
        $y = (int) now()->format('Y');

        return now()->month >= 8 ? "{$y}/".($y + 1) : ($y - 1)."/{$y}";
    }

    public function continue(): void
    {
        $data = $this->validate([
            'form.title' => ['nullable', 'string', 'max:255'],
            'form.tanggal_penyusunan' => ['required', 'date'],
            'form.tahun_akademik' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'form.nama_fakultas' => ['nullable', 'string', 'max:255'],
            'form.nama_prodi' => ['nullable', 'string', 'max:255'],
            'form.nama_kaprodi' => ['nullable', 'string', 'max:255'],
            'form.nama_dekan' => ['nullable', 'string', 'max:255'],
        ], ['form.tahun_akademik.regex' => 'Format tahun akademik: 2025/2026.'], ['form.tanggal_penyusunan' => 'tanggal penyusunan', 'form.tahun_akademik' => 'tahun akademik'])['form'];

        $session = $this->session();
        $session->fill([
            'title' => $data['title'] ?: 'Perangkat Pembelajaran '.$data['tahun_akademik'],
            'tanggal_penyusunan' => $data['tanggal_penyusunan'],
            'tahun_akademik' => $data['tahun_akademik'],
            'identity' => array_map(fn ($v) => trim((string) $v), array_intersect_key($data, array_flip(['nama_fakultas', 'nama_prodi', 'nama_kaprodi', 'nama_dekan']))),
        ])->save();

        $this->next();
    }

    public function render()
    {
        return view('modules.perangkat-pembelajaran.session-data-step');
    }
}
