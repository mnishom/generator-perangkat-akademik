<?php

namespace App\Core\Library;

use App\Core\Claude\ClaudeDocumentClient;
use App\Models\GenerationSession;
use App\Models\LibraryFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pustaka berkas masukan (kurikulum, contoh, template) dan pengunggahannya ke Files API.
 */
class FileLibrary
{
    public function __construct(private readonly ClaudeDocumentClient $client) {}

    public function store(UploadedFile $file, string $role, User $user, ?string $moduleKey = null, bool $shared = false, ?string $description = null): LibraryFile
    {
        $ext = Str::lower($file->getClientOriginalExtension());
        $dir = $shared ? 'library/shared' : 'library/prodi-'.($user->prodi_id ?? 0);
        $path = $file->storeAs($dir, Str::uuid().'.'.$ext, 'local');

        return LibraryFile::create([
            'prodi_id' => $shared ? null : $user->prodi_id,
            'uploaded_by' => $user->id,
            'role' => $role,
            'module_key' => $moduleKey,
            'is_shared' => $shared,
            'original_name' => $this->cleanName($file->getClientOriginalName(), $ext),
            'path' => $path,
            'extension' => $ext,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'description' => $description,
        ]);
    }

    /** Nama berkas aman untuk container: tanpa path, tanpa karakter aneh, ekstensi terjaga. */
    public function cleanName(string $name, string $ext): string
    {
        $base = pathinfo(basename(str_replace('\\', '/', $name)), PATHINFO_FILENAME);
        $base = trim(preg_replace('/[^\pL\pN\s._()-]+/u', '', $base)) ?: 'berkas';

        return Str::limit($base, 120, '').'.'.$ext;
    }

    /**
     * Berkas masukan sesi: peran => LibraryFile.
     *
     * @return array<string, LibraryFile>
     */
    public function sessionFiles(GenerationSession $session): array
    {
        $ids = array_filter($session->inputs ?? []);
        $files = LibraryFile::withTrashed()->whereIn('id', $ids)->get()->keyBy('id');

        $out = [];
        foreach ($ids as $role => $id) {
            if ($files->has($id)) {
                $out[$role] = $files[$id];
            }
        }

        return $out;
    }

    /**
     * Nama berkas di dalam container per peran; nama kembar diberi akhiran peran.
     *
     * @return array<string, string>
     */
    public function containerNames(GenerationSession $session): array
    {
        $names = [];
        $used = [];
        foreach ($this->sessionFiles($session) as $role => $file) {
            $name = $file->original_name;
            if (isset($used[Str::lower($name)])) {
                $name = pathinfo($name, PATHINFO_FILENAME).' ('.$role.').'.$file->extension;
            }
            $used[Str::lower($name)] = true;
            $names[$role] = $name;
        }

        return $names;
    }

    /**
     * Pastikan berkas peran-peran tersebut sudah ada di Files API untuk sesi ini.
     *
     * @param  string[]  $roles
     * @return array<string, string> peran => file_id
     */
    public function ensureRemote(GenerationSession $session, array $roles): array
    {
        return Cache::lock('session-remote-files-'.$session->id, 600)->block(600, function () use ($session, $roles) {
            $session->refresh();
            $remote = $session->option('remote_files', []);
            $files = $this->sessionFiles($session);
            $names = $this->containerNames($session);

            foreach ($roles as $role) {
                if (! isset($files[$role]) || isset($remote[$role])) {
                    continue;
                }
                $file = $files[$role];
                $remote[$role] = $this->client->uploadFile(
                    Storage::disk('local')->path($file->path),
                    $names[$role],
                    $file->mime ?: $this->mimeFor($file->extension),
                );
            }

            $session->options = array_merge($session->options ?? [], ['remote_files' => $remote]);
            $session->save();

            return array_intersect_key($remote, array_flip($roles));
        });
    }

    /** Hapus salinan berkas sesi dari Files API (berkas asli tetap di server aplikasi). */
    public function purgeRemote(GenerationSession $session): void
    {
        foreach ($session->option('remote_files', []) as $fileId) {
            try {
                $this->client->deleteFile($fileId);
            } catch (Throwable $e) {
                report($e);
            }
        }
        $session->options = array_merge($session->options ?? [], ['remote_files' => []]);
        $session->save();
    }

    public function mimeFor(string $ext): string
    {
        return match (Str::lower($ext)) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pdf' => 'application/pdf',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }
}
