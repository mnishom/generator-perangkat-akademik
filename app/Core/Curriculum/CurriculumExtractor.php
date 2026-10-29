<?php

namespace App\Core\Curriculum;

use App\Core\Claude\ClaudeDocumentClient;
use App\Core\Claude\GenerateRequest;
use App\Core\Curriculum\Jobs\ExtractCurriculum;
use App\Core\Generation\GenerationGuard;
use App\Core\Library\FileLibrary;
use App\Core\Modules\ModuleAccess;
use App\Core\Usage\UsageRecorder;
use App\Core\Validation\ReportParser;
use App\Models\CurriculumExtraction;
use App\Models\LibraryFile;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Layanan inti ekstraksi dan verifikasi data kurikulum. Hasil yang sudah
 * diverifikasi dapat dipakai ulang oleh modul mana pun selama dokumen
 * kurikulumnya sama (dicocokkan dengan hash SHA-256 berkas).
 */
class CurriculumExtractor
{
    public const OUTPUT_FILENAME = 'kurikulum.json';

    public function __construct(
        private readonly ClaudeDocumentClient $client,
        private readonly GenerationGuard $guard,
        private readonly ModuleAccess $access,
        private readonly AnomalyDetector $detector,
        private readonly UsageRecorder $usage,
        private readonly FileLibrary $files,
        private readonly Settings $settings,
    ) {}

    /** Hasil terverifikasi terbaru untuk dokumen kurikulum yang isinya sama. */
    public function findReusable(LibraryFile $file, ?int $prodiId): ?CurriculumExtraction
    {
        return CurriculumExtraction::query()
            ->where('sha256', $file->sha256)
            ->where('status', CurriculumExtraction::STATUS_VERIFIED)
            ->when($prodiId, fn ($q) => $q->where(fn ($q) => $q->where('prodi_id', $prodiId)->orWhereNull('prodi_id')))
            ->latest('verified_at')
            ->first();
    }

    public function start(LibraryFile $file, User $user, string $moduleKey): CurriculumExtraction
    {
        if ($reason = $this->guard->deny($user, $moduleKey)) {
            throw new RuntimeException($reason);
        }
        $this->guard->throttle($user);

        $extraction = CurriculumExtraction::create([
            'library_file_id' => $file->id,
            'sha256' => $file->sha256,
            'prodi_id' => $user->prodi_id,
            'user_id' => $user->id,
            'module_key' => $moduleKey,
            'status' => CurriculumExtraction::STATUS_QUEUED,
            'model' => $this->access->modelFor($moduleKey),
        ]);

        ExtractCurriculum::dispatch($extraction->id);

        return $extraction;
    }

    /** Dijalankan oleh Job. Galat sementara dilempar ulang agar Job dicoba lagi. */
    public function run(CurriculumExtraction $extraction): void
    {
        $user = User::find($extraction->user_id);
        if ($user && ($reason = $this->guard->deny($user, (string) $extraction->module_key))) {
            $extraction->update(['status' => CurriculumExtraction::STATUS_BLOCKED, 'error' => $reason]);

            return;
        }

        $extraction->update(['status' => CurriculumExtraction::STATUS_RUNNING, 'error' => null]);
        $file = $extraction->file;
        $remoteIds = [];

        try {
            $remoteIds[] = $uploaded = $this->client->uploadFile(
                Storage::disk('local')->path($file->path),
                $file->original_name,
                $file->mime ?: $this->files->mimeFor($file->extension),
            );

            $result = $this->client->generate(new GenerateRequest(
                model: $extraction->model ?: $this->settings->defaultModel(),
                messages: [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'container_upload', 'file_id' => $uploaded],
                        ['type' => 'text', 'text' => $this->prompt($file->original_name)],
                    ],
                ]],
                system: $this->systemPrompt(),
                skills: $file->extension === 'pdf' ? ['pdf', 'docx'] : ['docx'],
            ));

            $this->usage->record($result, $extraction->model, [
                'user_id' => $extraction->user_id,
                'prodi_id' => $extraction->prodi_id,
                'module_key' => $extraction->module_key,
                'kind' => 'extract',
            ]);

            $raw = null;
            foreach (array_reverse($result->outputFiles) as $out) {
                $remoteIds[] = $out['file_id'];
                $name = $out['filename'] ?? $this->client->fileMetadata($out['file_id'])['filename'];
                if ($raw === null && str_ends_with((string) $name, '.json')) {
                    $raw = $this->client->downloadFile($out['file_id']);
                }
            }

            $decoded = $raw !== null ? json_decode($raw, true) : ReportParser::parse($result->text);
            if (! is_array($decoded) || ! isset($decoded['mata_kuliah'])) {
                $extraction->update([
                    'status' => CurriculumExtraction::STATUS_FAILED,
                    'error' => 'Hasil ekstraksi tidak berbentuk JSON yang sesuai skema.',
                    'raw' => $raw ?? $result->text,
                ]);

                return;
            }

            $data = CurriculumData::normalize($decoded);
            $extraction->update([
                'status' => CurriculumExtraction::STATUS_EXTRACTED,
                'raw' => $raw ?? json_encode($decoded, JSON_UNESCAPED_UNICODE),
                'data' => $data,
                'anomalies' => $this->detector->detect($data),
                'input_tokens' => $result->inputTokens(),
                'output_tokens' => $result->outputTokens(),
            ]);
        } finally {
            if ($this->settings->get('delete_remote_files')) {
                foreach ($remoteIds as $id) {
                    try {
                        $this->client->deleteFile($id);
                    } catch (Throwable) {
                    }
                }
            }
        }
    }

    /** Simpan suntingan pengguna; bila $verify, tandai sebagai sumber data utama. */
    public function save(CurriculumExtraction $extraction, array $data, User $user, bool $verify): CurriculumExtraction
    {
        $data = CurriculumData::normalize($data);
        $extraction->fill([
            'data' => $data,
            'anomalies' => $this->detector->detect($data),
        ]);
        if ($verify) {
            $extraction->fill([
                'status' => CurriculumExtraction::STATUS_VERIFIED,
                'verified_by' => $user->id,
                'verified_at' => now(),
            ]);
        }
        $extraction->save();

        return $extraction;
    }

    public function systemPrompt(): string
    {
        return <<<'TXT'
Anda adalah asisten penjaminan mutu akademik yang mengekstrak data kurikulum OBE secara teliti dan apa adanya.
- Ambil data HANYA dari dokumen. Jangan mengarang, menebak, atau melengkapi data yang tidak tertulis; isi null atau daftar kosong bila tidak ada.
- Salin kode dan rumusan CPL/CPMK persis seperti di dokumen (huruf, tanda baca, penomoran).
- Gunakan kode Python (python-docx, pdfplumber/pypdf) untuk membaca seluruh tabel dan teks, termasuk tabel pemetaan MK–CPL–CPMK, sebaran MK per semester, dan deskripsi MK. Periksa kembali bahwa tidak ada MK yang terlewat.
TXT;
    }

    public function prompt(string $filename): string
    {
        $schema = CurriculumData::schemaDescription();
        $out = self::OUTPUT_FILENAME;

        return <<<TXT
Baca dokumen kurikulum OBE "{$filename}" dan ekstrak data terstruktur untuk SETIAP mata kuliah di dalamnya, beserta daftar seluruh CPL prodi dan profil lulusan.

Untuk setiap MK: kode MK, nama MK, bobot SKS (total dan rincian T/P/L/S bila tersedia), semester, MK prasyarat, jenis MK (teori, praktikum, teori_praktikum, magang_pkl, tugas_akhir, atau lainnya — tentukan dari rincian SKS dan nama/deskripsi MK), daftar CPL yang dibebankan (kode dan rumusan), daftar CPMK (kode, rumusan, dan kode CPL yang dipetakan), bahan kajian, deskripsi MK, serta pustaka bila tercantum.

Ketentuan penting (dokumen kurikulum tiap prodi berbeda tata letaknya):
- SKS dan semester: utamakan tabel distribusi/sebaran MK per semester. Bila tabel lain (mis. organisasi/kelompok MK) mencantumkan angka berbeda, tetap pakai tabel distribusi dan tulis perbedaannya di `catatan_ekstraksi`.
- Peminatan/konsentrasi: bila sebuah semester memiliki beberapa tabel peminatan, cantumkan setiap MK SATU KALI saja. Isi `peminatan` dengan semua peminatan yang memuat MK tersebut; MK yang muncul di semua peminatan semester itu juga diisi semua nama peminatannya. MK wajib di luar peminatan: `peminatan` kosong.
- CPL per MK: bila tidak tertulis terpisah, turunkan dari tabel pemetaan/bobot CPMK→CPL (CPL yang memiliki bobot pada CPMK MK tersebut). Simpan bobotnya di `bobot_cpl` setiap CPMK.
- Bahan kajian: bila hanya ditulis kode (mis. "BK93; BK103"), cari namanya di tabel bahan kajian dan tulis "kode – nama".
- Abaikan contoh/format RPS, silabus, atau dokumen lain yang dilampirkan di dalam dokumen kurikulum.
- Data yang tidak ada di dokumen (mis. deskripsi MK, prasyarat, pustaka) biarkan null/kosong; jangan dikarang.

Tulis hasilnya sebagai JSON UTF-8 yang valid ke berkas `\$OUTPUT_DIR/{$out}` dengan skema berikut:
{$schema}

Setelah berkas tersimpan, balas singkat: jumlah MK, jumlah CPL, jumlah peminatan, total SKS per jalur (MK wajib + satu peminatan), dan total SKS yang tertulis di dokumen (bila ada).
TXT;
    }
}
