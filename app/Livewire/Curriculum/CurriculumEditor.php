<?php

namespace App\Livewire\Curriculum;

use App\Core\Curriculum\AnomalyDetector;
use App\Core\Curriculum\CurriculumData;
use App\Core\Curriculum\CurriculumExtractor;
use App\Models\CurriculumExtraction;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * Tabel data kurikulum hasil ekstraksi yang dapat diedit dan diverifikasi.
 * Komponen inti; dipakai di wizard modul mana pun yang membutuhkan data kurikulum.
 */
class CurriculumEditor extends Component
{
    #[Locked]
    public int $extractionId;

    /** Tampilkan kotak centang pemilihan MK (dihubungkan ke induk via wire:model). */
    #[Locked]
    public bool $selectable = false;

    #[Modelable]
    public array $selected = [];

    public array $data = [];

    public ?int $editing = null;

    public array $form = [];

    public string $tab = 'mk';

    public function mount(int $extractionId, bool $selectable = false): void
    {
        $extraction = CurriculumExtraction::findOrFail($extractionId);
        $this->authorizeAccess($extraction);
        $this->extractionId = $extractionId;
        $this->selectable = $selectable;
        $this->data = $extraction->data ?? CurriculumData::normalize([]);
    }

    private function authorizeAccess(CurriculumExtraction $e): void
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $e->user_id === $user->id || ($e->prodi_id && $e->prodi_id === $user->prodi_id), 403);
    }

    private function extraction(): CurriculumExtraction
    {
        $e = CurriculumExtraction::findOrFail($this->extractionId);
        $this->authorizeAccess($e);

        return $e;
    }

    // -------------------------------------------------------- Ubah satu MK

    public function edit(int $index): void
    {
        $mk = $this->data['mata_kuliah'][$index] ?? null;
        abort_unless($mk, 404);
        $this->editing = $index;
        $this->form = [
            'kode' => $mk['kode'],
            'nama' => $mk['nama'],
            'sks_total' => $mk['sks']['total'],
            'sks_teori' => $mk['sks']['teori'],
            'sks_praktikum' => $mk['sks']['praktikum'],
            'sks_lapangan' => $mk['sks']['lapangan'],
            'sks_seminar' => $mk['sks']['seminar'],
            'semester' => $mk['semester'],
            'jenis' => $mk['jenis'],
            'prasyarat' => implode(', ', $mk['prasyarat']),
            'cpl' => array_column($mk['cpl'], 'kode'),
            'cpmk' => array_map(fn ($c) => ['kode' => $c['kode'], 'rumusan' => $c['rumusan'], 'cpl' => implode(', ', $c['cpl'])], $mk['cpmk']),
            'bahan_kajian' => implode("\n", $mk['bahan_kajian']),
            'deskripsi' => $mk['deskripsi'],
            'pustaka' => implode("\n", $mk['pustaka']),
        ];
        $this->resetValidation();
    }

    public function addCourse(): void
    {
        $this->data['mata_kuliah'][] = CurriculumData::normalize(['mata_kuliah' => [['kode' => '', 'nama' => '']]])['mata_kuliah'][0];
        $this->edit(array_key_last($this->data['mata_kuliah']));
    }

    public function removeCourse(int $index): void
    {
        $kode = $this->data['mata_kuliah'][$index]['kode'] ?? null;
        unset($this->data['mata_kuliah'][$index]);
        $this->data['mata_kuliah'] = array_values($this->data['mata_kuliah']);
        $this->selected = array_values(array_diff($this->selected, [$kode]));
        $this->editing = null;
        $this->persist(false);
    }

    public function addCpmk(): void
    {
        $this->form['cpmk'][] = ['kode' => '', 'rumusan' => '', 'cpl' => ''];
    }

    public function removeCpmk(int $i): void
    {
        unset($this->form['cpmk'][$i]);
        $this->form['cpmk'] = array_values($this->form['cpmk']);
    }

    public function saveCourse(): void
    {
        $this->validate([
            'form.kode' => ['required', 'string', 'max:32'],
            'form.nama' => ['required', 'string', 'max:255'],
            'form.sks_total' => ['required', 'integer', 'min:0', 'max:40'],
            'form.semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'form.jenis' => ['required', 'in:'.implode(',', CurriculumData::JENIS_MK)],
            'form.cpmk.*.kode' => ['required', 'string', 'max:32'],
            'form.cpmk.*.rumusan' => ['required', 'string'],
        ], attributes: [
            'form.kode' => 'kode MK', 'form.nama' => 'nama MK', 'form.sks_total' => 'SKS', 'form.cpmk.*.kode' => 'kode CPMK', 'form.cpmk.*.rumusan' => 'rumusan CPMK',
        ]);

        $f = $this->form;
        $old = $this->data['mata_kuliah'][$this->editing]['kode'] ?? null;
        $cplIndex = collect($this->data['cpl'])->keyBy('kode');
        $split = fn (string $s) => array_values(array_filter(array_map('trim', preg_split('/[,;\n]/', $s))));

        $this->data['mata_kuliah'][$this->editing] = [
            'kode' => trim($f['kode']),
            'nama' => trim($f['nama']),
            'sks' => [
                'total' => (int) $f['sks_total'],
                'teori' => $this->intOrNull($f['sks_teori']),
                'praktikum' => $this->intOrNull($f['sks_praktikum']),
                'lapangan' => $this->intOrNull($f['sks_lapangan']),
                'seminar' => $this->intOrNull($f['sks_seminar']),
            ],
            'semester' => $this->intOrNull($f['semester']),
            'prasyarat' => $split($f['prasyarat'] ?? ''),
            'jenis' => $f['jenis'],
            'cpl' => array_values(array_map(fn ($k) => ['kode' => $k, 'rumusan' => $cplIndex[$k]['rumusan'] ?? ''], array_filter($f['cpl'] ?? []))),
            'cpmk' => array_values(array_map(fn ($c) => ['kode' => trim($c['kode']), 'rumusan' => trim($c['rumusan']), 'cpl' => $split($c['cpl'] ?? '')], $f['cpmk'] ?? [])),
            'bahan_kajian' => $split(str_replace(';', "\n", $f['bahan_kajian'] ?? '')),
            'deskripsi' => trim((string) ($f['deskripsi'] ?? '')) ?: null,
            'pustaka' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($f['pustaka'] ?? ''))))),
        ];

        if ($old && $old !== $this->data['mata_kuliah'][$this->editing]['kode'] && in_array($old, $this->selected, true)) {
            $this->selected = array_values(array_merge(array_diff($this->selected, [$old]), [$this->data['mata_kuliah'][$this->editing]['kode']]));
        }

        $this->editing = null;
        $this->persist(false);
        $this->dispatch('toast', type: 'success', message: 'Data MK diperbarui.');
    }

    // ------------------------------------------------------ CPL & profil

    public function addCpl(): void
    {
        $this->data['cpl'][] = ['kode' => '', 'rumusan' => ''];
    }

    public function removeCpl(int $i): void
    {
        unset($this->data['cpl'][$i]);
        $this->data['cpl'] = array_values($this->data['cpl']);
    }

    public function saveCpl(): void
    {
        $this->persist(false);
        $this->dispatch('toast', type: 'success', message: 'Daftar CPL & data prodi disimpan.');
    }

    // ------------------------------------------------------ Verifikasi

    public function verify(CurriculumExtractor $extractor, AnomalyDetector $detector): void
    {
        $extraction = $this->persist(true);
        $errors = $detector->errorCount($extraction->anomalies ?? []);

        $this->dispatch('curriculum-verified', extractionId: $extraction->id);
        $this->dispatch('toast', type: $errors ? 'warning' : 'success', message: $errors
            ? "Data diverifikasi dengan {$errors} catatan galat. Pastikan sudah benar sebelum generate."
            : 'Data kurikulum diverifikasi dan menjadi sumber data utama.');
    }

    public function selectAll(bool $on): void
    {
        $this->selected = $on ? array_values(array_filter(array_column($this->data['mata_kuliah'], 'kode'))) : [];
    }

    public function selectSemester(int $semester): void
    {
        $codes = collect($this->data['mata_kuliah'])->where('semester', $semester)->pluck('kode')->filter()->all();
        $this->selected = array_values(array_unique(array_merge($this->selected, $codes)));
    }

    private function persist(bool $verify): CurriculumExtraction
    {
        $extraction = $this->extraction();
        $extraction = app(CurriculumExtractor::class)->save($extraction, $this->data, auth()->user(), $verify);
        $this->data = $extraction->data;

        return $extraction;
    }

    private function intOrNull(mixed $v): ?int
    {
        return $v === null || $v === '' ? null : (int) $v;
    }

    public function render()
    {
        $extraction = $this->extraction();
        $anomalies = $extraction->anomalies ?? ['global' => [], 'courses' => []];

        return view('livewire.curriculum.curriculum-editor', [
            'extraction' => $extraction,
            'anomalies' => $anomalies,
            'errorCount' => app(AnomalyDetector::class)->errorCount($anomalies),
            'semesters' => collect($this->data['mata_kuliah'])->pluck('semester')->filter()->unique()->sort()->values(),
            'jenisLabels' => CurriculumData::JENIS_LABEL,
        ]);
    }
}
