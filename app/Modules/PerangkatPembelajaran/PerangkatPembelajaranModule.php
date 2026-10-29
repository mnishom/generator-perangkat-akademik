<?php

namespace App\Modules\PerangkatPembelajaran;

use App\Core\Curriculum\CurriculumData;
use App\Core\Library\FileLibrary;
use App\Core\Modules\DescribesPrompts;
use App\Core\Modules\DocumentTypeDefinition;
use App\Core\Modules\InputRequirement;
use App\Core\Modules\JobPlan;
use App\Core\Modules\JobStep;
use App\Core\Modules\ModuleContract;
use App\Core\Prompts\PromptEngine;
use App\Core\Validation\Validators\LeftoverPlaceholderValidator;
use App\Livewire\Wizard\PromptStep;
use App\Models\GenerationItem;
use App\Models\GenerationSession;
use App\Modules\PerangkatPembelajaran\Validators\CourseIdentityValidator;
use App\Modules\PerangkatPembelajaran\Validators\CpmkCodesValidator;
use App\Modules\PerangkatPembelajaran\Validators\KontrakConsistencyValidator;
use App\Modules\PerangkatPembelajaran\Validators\RpsReportValidator;
use App\Modules\PerangkatPembelajaran\Validators\SilabusCompletenessValidator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Modul 1: Perangkat Pembelajaran — RPS dan Kontrak Perkuliahan per MK, serta
 * satu Silabus Prodi untuk seluruh MK.
 */
class PerangkatPembelajaranModule implements DescribesPrompts, ModuleContract
{
    public const KEY = 'perangkat_pembelajaran';

    /** Maksimum MK per tahap penyusunan Silabus. */
    public const SILABUS_GROUP_SIZE = 10;

    public function __construct(
        private readonly PromptEngine $prompts,
        private readonly FileLibrary $files,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function name(): string
    {
        return 'Perangkat Pembelajaran';
    }

    public function description(): string
    {
        return 'RPS dan Kontrak Perkuliahan per mata kuliah, serta Silabus Prodi untuk seluruh mata kuliah, mengikuti template resmi yang Anda unggah.';
    }

    public function icon(): string
    {
        return 'academic';
    }

    public function documentTypes(): array
    {
        return [
            'rps' => new DocumentTypeDefinition('rps', 'Rencana Pembelajaran Semester (RPS)', ['docx'], 'docx', 'template_rps', 'RPS_{kode_mk}_{nama_mk}.docx', 'item', 'RPS'),
            'kontrak' => new DocumentTypeDefinition('kontrak', 'Kontrak Perkuliahan', ['docx'], 'docx', 'template_kontrak', 'Kontrak_{kode_mk}_{nama_mk}.docx', 'item', 'Kontrak'),
            'silabus' => new DocumentTypeDefinition('silabus', 'Silabus Prodi', ['docx'], 'docx', 'template_silabus', 'Silabus_{nama_prodi}_{tahun_akademik}.docx', 'session', 'Silabus'),
        ];
    }

    public function inputRequirements(): array
    {
        return [
            new InputRequirement('kurikulum', 'Dokumen Kurikulum OBE', true, ['docx', 'pdf'], help: 'Berisi seluruh MK beserta peta CPL, CPMK, bahan kajian, SKS, dan semester.'),
            new InputRequirement('contoh_rps', 'Contoh RPS Final', true, ['docx', 'pdf'], help: 'RPS yang sudah lengkap dan disetujui; dipakai sebagai acuan gaya dan kedalaman isi saja.'),
            new InputRequirement('template_rps', 'Template RPS', false, ['docx']),
            new InputRequirement('template_kontrak', 'Template Kontrak Perkuliahan', false, ['docx']),
            new InputRequirement('template_silabus', 'Template Silabus', false, ['docx']),
            new InputRequirement('kurikulum_terverifikasi', 'Data kurikulum terverifikasi', true, source: InputRequirement::SOURCE_CURRICULUM),
        ];
    }

    public function wizardSteps(): array
    {
        return [
            'data_sesi' => ['label' => 'Data sesi', 'component' => Steps\SessionDataStep::class],
            'kurikulum' => ['label' => 'Kurikulum', 'component' => Steps\KurikulumStep::class],
            'contoh_rps' => ['label' => 'Contoh RPS', 'component' => Steps\ContohRpsStep::class],
            'template' => ['label' => 'Template', 'component' => Steps\TemplateStep::class],
            'verifikasi' => ['label' => 'Verifikasi data', 'component' => Steps\VerifikasiStep::class],
            'prompt' => ['label' => 'Prompt', 'component' => PromptStep::class],
            'uji_coba' => ['label' => 'Uji coba', 'component' => Steps\UjiCobaStep::class],
            'generate' => ['label' => 'Generate', 'component' => Steps\GenerateStep::class],
        ];
    }

    public function defaultPrompts(): array
    {
        return [
            'rps_kontrak' => resource_path('prompts/rps_kontrak.md'),
            'silabus' => resource_path('prompts/silabus.md'),
        ];
    }

    public function promptDefinitions(?GenerationSession $session = null): array
    {
        $file = [
            'file_kurikulum' => ['description' => 'Nama berkas dokumen kurikulum', 'required' => false],
            'file_contoh_rps' => ['description' => 'Nama berkas contoh RPS', 'required' => false],
            'file_template_rps' => ['description' => 'Nama berkas template RPS', 'required' => false],
            'file_template_kontrak' => ['description' => 'Nama berkas template Kontrak', 'required' => false],
            'file_template_silabus' => ['description' => 'Nama berkas template Silabus', 'required' => false],
        ];
        $sesi = [
            'nama_prodi' => ['description' => 'Nama program studi'],
            'nama_fakultas' => ['description' => 'Nama fakultas'],
            'nama_kaprodi' => ['description' => 'Nama Ketua Program Studi'],
            'nama_dekan' => ['description' => 'Nama Dekan'],
            'tanggal_penyusunan' => ['description' => 'Tanggal penyusunan dokumen'],
            'tahun_akademik' => ['description' => 'Tahun akademik'],
        ];

        $defs = [
            'rps_kontrak' => [
                'label' => 'Prompt RPS & Kontrak Perkuliahan',
                'description' => 'Dipakai untuk setiap MK yang dipilih (satu Job per MK: RPS lalu Kontrak di container yang sama).',
                'variables' => $file + [
                    'nama_mk' => ['description' => 'Nama mata kuliah', 'required' => true],
                    'kode_mk' => ['description' => 'Kode mata kuliah', 'required' => true],
                    'sks' => ['description' => 'Bobot SKS'],
                    'semester' => ['description' => 'Semester'],
                    'jenis_mk' => ['description' => 'Jenis MK (teori/praktikum/…)'],
                    'data_mk' => ['description' => 'Seluruh data MK terverifikasi (CPL, CPMK, bahan kajian, …)', 'required' => true],
                ] + $sesi,
            ],
            'silabus' => [
                'label' => 'Prompt Silabus Prodi',
                'description' => 'Dipakai untuk satu dokumen Silabus berisi seluruh MK (disusun bertahap per semester).',
                'variables' => $file + $sesi + [
                    'daftar_mk' => ['description' => 'Daftar seluruh MK terverifikasi per semester', 'required' => true],
                ],
            ],
        ];

        if ($session) {
            $inputs = $session->inputs ?? [];
            if (empty($inputs['template_rps'])) {
                unset($defs['rps_kontrak']);
            }
            if (empty($inputs['template_silabus']) || ! $session->option('generate_silabus')) {
                unset($defs['silabus']);
            }
        }

        return $defs;
    }

    public function systemInstructions(string $docType): string
    {
        // Instruksi lengkap dibentuk per Job (butuh nama berkas & identitas sesi), lihat planJobs().
        return '';
    }

    public function reportSchema(string $docType): array
    {
        return match ($docType) {
            'rps' => [
                'kode_mk' => 'string', 'sks' => 'integer', 'cpmk_digunakan' => 'daftar kode CPMK',
                'komponen_penilaian' => 'daftar {nama, bobot} (bobot dalam persen)',
                'total_bobot' => 'angka (persen)', 'bobot_konsisten' => 'boolean', 'jumlah_pertemuan' => 'integer',
                'minggu_uts' => 'integer|null', 'minggu_uas' => 'integer|null', 'cpmk_tanpa_subcpmk' => 'daftar kode CPMK',
                'metode_pembelajaran' => 'daftar', 'pustaka_perlu_verifikasi' => 'integer (jumlah)', 'catatan' => 'daftar',
            ],
            'kontrak' => [
                'komponen_penilaian' => 'daftar {nama, bobot} (bobot dalam persen)', 'konsisten_dengan_rps' => 'boolean', 'catatan' => 'daftar',
            ],
            'silabus' => [
                'jumlah_mk_di_kurikulum' => 'integer', 'jumlah_mk_di_dokumen' => 'integer', 'mk_terlewat' => 'daftar kode MK', 'catatan' => 'daftar',
            ],
            default => [],
        };
    }

    public function validators(string $docType): array
    {
        return match ($docType) {
            'rps' => [new CourseIdentityValidator, new CpmkCodesValidator, new RpsReportValidator, new LeftoverPlaceholderValidator],
            'kontrak' => [new CourseIdentityValidator(checkSks: false), new KontrakConsistencyValidator, new LeftoverPlaceholderValidator],
            'silabus' => [new SilabusCompletenessValidator, new LeftoverPlaceholderValidator],
            default => [],
        };
    }

    public function planJobs(GenerationSession $session): array
    {
        $names = $this->files->containerNames($session);
        $plans = [];

        if (isset($names['template_rps'])) {
            foreach ($session->items as $item) {
                $plans[] = $this->coursePlan($session, $item, $names);
            }
        }

        if (isset($names['template_silabus']) && $session->option('generate_silabus')) {
            $plans[] = $this->silabusPlan($session, $names);
        }

        return $plans;
    }

    // ------------------------------------------------------------------

    private function coursePlan(GenerationSession $session, GenerationItem $item, array $names): JobPlan
    {
        $mk = $item->input;
        $vars = $this->variables($session, $names) + self::courseVariables($mk);
        $userPrompt = $this->prompts->render($this->sessionPrompt($session, 'rps_kontrak'), $vars);
        $rpsName = self::filename('RPS', $mk['kode'], $mk['nama']);
        $hasKontrak = isset($names['template_kontrak']);
        $kontrakName = $hasKontrak ? self::filename('Kontrak', $mk['kode'], $mk['nama']) : null;
        $system = SystemInstructions::forCourse($names, $this->identity($session), $rpsName, $kontrakName);
        $roles = array_keys(array_intersect_key($names, array_flip(['kurikulum', 'contoh_rps', 'template_rps', 'template_kontrak'])));

        $steps = [new JobStep(
            key: 'rps',
            label: 'RPS',
            prompt: $userPrompt."\n\n---\n".self::stageNote(1, $hasKontrak ? 2 : 1)
                ."Pada tahap ini susun **RPS saja** untuk {$mk['nama']} ({$mk['kode']})"
                .($hasKontrak ? '; Kontrak Perkuliahan akan diminta pada pesan berikutnya' : '')
                .". Simpan sebagai `\$OUTPUT_DIR/{$rpsName}`.\n\n".self::reportInstruction($this->reportSchema('rps')),
            docType: 'rps',
            inputRoles: $roles,
            outputFilename: $rpsName,
            system: $system,
        )];

        if ($hasKontrak) {
            $steps[] = new JobStep(
                key: 'kontrak',
                label: 'Kontrak Perkuliahan',
                prompt: self::stageNote(2, 2)."Sekarang susun **Kontrak Perkuliahan** untuk {$mk['nama']} ({$mk['kode']}) berdasarkan RPS yang baru Anda susun, menggunakan template Kontrak Perkuliahan \"{$names['template_kontrak']}\", sesuai permintaan pada pesan sebelumnya. Komponen dan bobot penilaian, jadwal, dan pustaka wajib sama dengan RPS. Simpan sebagai `\$OUTPUT_DIR/{$kontrakName}`.\n\n"
                    .self::reportInstruction($this->reportSchema('kontrak')),
                docType: 'kontrak',
                inputRoles: $roles,
                outputFilename: $kontrakName,
                system: $system,
            );
        }

        return new JobPlan("mk:{$mk['kode']}", "{$mk['kode']} {$mk['nama']}", $steps, $item, ['docx']);
    }

    private function silabusPlan(GenerationSession $session, array $names): JobPlan
    {
        $courses = $session->items->pluck('input')->all();
        $vars = $this->variables($session, $names) + ['daftar_mk' => CurriculumData::coursesToList($courses)];
        $userPrompt = $this->prompts->render($this->sessionPrompt($session, 'silabus'), $vars);
        $filename = self::silabusFilename($session);
        $system = SystemInstructions::forSilabus($names, $this->identity($session), $filename);
        $roles = array_keys(array_intersect_key($names, array_flip(['kurikulum', 'template_silabus'])));

        $groups = self::silabusGroups($courses);
        $total = count($groups);
        $steps = [];
        foreach ($groups as $i => $group) {
            $n = $i + 1;
            $last = $n === $total;
            $detail = implode("\n\n", array_map(fn ($mk) => CurriculumData::courseToText($mk), $group['courses']));
            $text = ($n === 1 ? $userPrompt."\n\n---\n" : '')
                .self::stageNote($n, $total)
                ."Kelompok ini: **{$group['label']}** (".count($group['courses']).' MK). '
                .($n === 1 ? 'Mulai dokumen Silabus dari template dan isi bagian untuk kelompok ini.' : 'Lanjutkan dokumen kerja Silabus yang sama dan tambahkan bagian untuk kelompok ini sesuai urutan semester.')
                ."\n\nData lengkap MK kelompok ini (terverifikasi):\n{$detail}\n\n"
                .($last
                    ? 'Ini tahap terakhir. Periksa bahwa SEMUA '.count($courses)." MK pada daftar sudah tercantum, rapikan dokumen, lalu simpan dokumen final sebagai `\$OUTPUT_DIR/{$filename}`.\n\n".self::reportInstruction($this->reportSchema('silabus'))
                    : 'Simpan dokumen kerja di `/tmp/kerja/` (JANGAN ke $OUTPUT_DIR). Balas singkat MK yang sudah ditambahkan.');

            $steps[] = new JobStep(
                key: 'silabus_'.$n,
                label: "Silabus ({$group['label']})",
                prompt: $text,
                docType: $last ? 'silabus' : null,
                inputRoles: $roles,
                outputFilename: $last ? $filename : null,
                collectsOutput: $last,
                system: $system,
                meta: ['group' => $group['label'], 'courses' => array_column($group['courses'], 'kode')],
            );
        }

        return new JobPlan('silabus', 'Silabus Prodi', $steps, null, ['docx']);
    }

    /**
     * Kelompokkan MK per semester; kelompok besar dipecah maksimal SILABUS_GROUP_SIZE MK.
     *
     * @return array<int, array{label: string, courses: array}>
     */
    public static function silabusGroups(array $courses): array
    {
        $groups = [];
        $bySemester = collect($courses)->groupBy(fn ($mk) => $mk['semester'] ?? 0)->sortKeys();
        foreach ($bySemester as $sem => $list) {
            $chunks = $list->values()->chunk(self::SILABUS_GROUP_SIZE);
            foreach ($chunks as $ci => $chunk) {
                $label = $sem ? "Semester {$sem}" : 'MK tanpa semester';
                if ($chunks->count() > 1) {
                    $label .= ' bagian '.($ci + 1);
                }
                $groups[] = ['label' => $label, 'courses' => $chunk->values()->all()];
            }
        }

        return $groups;
    }

    /** Variabel tingkat sesi + nama berkas. */
    public function variables(GenerationSession $session, ?array $names = null): array
    {
        $names ??= $this->files->containerNames($session);
        $identity = $this->identity($session);

        return [
            'file_kurikulum' => $names['kurikulum'] ?? '',
            'file_contoh_rps' => $names['contoh_rps'] ?? '',
            'file_template_rps' => $names['template_rps'] ?? '',
            'file_template_kontrak' => $names['template_kontrak'] ?? '',
            'file_template_silabus' => $names['template_silabus'] ?? '',
            'nama_prodi' => $identity['nama_prodi'] ?? '',
            'nama_fakultas' => $identity['nama_fakultas'] ?? '',
            'nama_kaprodi' => $identity['nama_kaprodi'] ?? '',
            'nama_dekan' => $identity['nama_dekan'] ?? '',
            'tanggal_penyusunan' => $session->tanggal_penyusunan
                ? Carbon::parse($session->tanggal_penyusunan)->locale('id')->translatedFormat('j F Y')
                : '',
            'tahun_akademik' => (string) $session->tahun_akademik,
        ];
    }

    public static function courseVariables(array $mk): array
    {
        return [
            'nama_mk' => $mk['nama'] ?? '',
            'kode_mk' => $mk['kode'] ?? '',
            'sks' => (string) ($mk['sks']['total'] ?? ''),
            'semester' => (string) ($mk['semester'] ?? ''),
            'jenis_mk' => CurriculumData::JENIS_LABEL[$mk['jenis'] ?? 'lainnya'] ?? ($mk['jenis'] ?? ''),
            'data_mk' => CurriculumData::courseToText($mk),
        ];
    }

    public function identity(GenerationSession $session): array
    {
        return array_map(fn ($v) => is_string($v) ? trim($v) : $v, $session->identity ?? []);
    }

    public function sessionPrompt(GenerationSession $session, string $key): string
    {
        return $session->prompts[$key] ?? $this->prompts->defaultBody($this, $key);
    }

    /** Mis. RPS_DTI3019_Pemrograman-Berorientasi-Objek.docx */
    public static function filename(string $prefix, string $kode, string $nama): string
    {
        return $prefix.'_'.self::slug($kode).'_'.self::slug($nama).'.docx';
    }

    /** Mis. Silabus_D4-Teknik-Informatika_2025-2026.docx */
    public static function silabusFilename(GenerationSession $session): string
    {
        $prodi = trim((string) ($session->identity['nama_prodi'] ?? '')) ?: ($session->prodi?->namaLengkap() ?: 'Prodi');

        return 'Silabus_'.self::slug($prodi).($session->tahun_akademik ? '_'.self::slug($session->tahun_akademik) : '').'.docx';
    }

    public static function slug(string $s): string
    {
        $s = preg_replace('/[^\pL\pN]+/u', '-', Str::ascii($s));

        return trim($s, '-') ?: 'x';
    }

    private static function stageNote(int $n, int $total): string
    {
        return $total > 1 ? "**Tahap {$n} dari {$total}.** " : '';
    }

    private static function reportInstruction(array $schema): string
    {
        $fields = implode("\n", array_map(fn ($k, $v) => "- `{$k}`: {$v}", array_keys($schema), $schema));

        return "Akhiri jawaban dengan satu blok ```json berisi laporan pemeriksaan dengan kolom:\n{$fields}";
    }
}
