<?php

namespace App\Core\Prompts;

use App\Core\Modules\DescribesPrompts;
use App\Core\Modules\ModuleContract;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mesin prompt inti: prompt default per modul (dengan riwayat versi), prompt
 * pribadi per pengguna, penggantian variabel {{nama}}, dan analisis variabel.
 */
class PromptEngine
{
    /** Isi prompt default aktif; versi 1 diambil dari berkas modul bila belum ada di database. */
    public function defaultBody(ModuleContract $module, string $key): string
    {
        $active = Prompt::where('module_key', $module->key())->where('prompt_key', $key)
            ->where('kind', Prompt::KIND_DEFAULT)->where('is_active', true)->latest('version')->first();

        return $active?->body ?? $this->fileBody($module, $key);
    }

    public function fileBody(ModuleContract $module, string $key): string
    {
        $path = $module->defaultPrompts()[$key] ?? null;

        return $path && is_file($path) ? trim(file_get_contents($path))."\n" : '';
    }

    /** Simpan versi baru prompt default (admin). */
    public function saveDefault(ModuleContract $module, string $key, string $body, User $by, ?string $note = null): Prompt
    {
        return DB::transaction(function () use ($module, $key, $body, $by, $note) {
            $query = Prompt::where('module_key', $module->key())->where('prompt_key', $key)->where('kind', Prompt::KIND_DEFAULT);

            if (! (clone $query)->exists()) {
                // Abadikan isi berkas asal sebagai versi 1 agar dapat dikembalikan.
                Prompt::create([
                    'module_key' => $module->key(), 'prompt_key' => $key, 'kind' => Prompt::KIND_DEFAULT,
                    'version' => 1, 'body' => $this->fileBody($module, $key), 'is_active' => false, 'note' => 'Bawaan aplikasi',
                ]);
            }

            $version = (int) (clone $query)->max('version') + 1;
            (clone $query)->update(['is_active' => false]);

            return Prompt::create([
                'module_key' => $module->key(), 'prompt_key' => $key, 'kind' => Prompt::KIND_DEFAULT,
                'version' => $version, 'body' => $body, 'is_active' => true, 'note' => $note, 'created_by' => $by->id,
            ]);
        });
    }

    /** Kembalikan prompt default ke versi tertentu (versi tersebut menjadi aktif). */
    public function activateVersion(ModuleContract $module, string $key, int $version): Prompt
    {
        return DB::transaction(function () use ($module, $key, $version) {
            $query = Prompt::where('module_key', $module->key())->where('prompt_key', $key)->where('kind', Prompt::KIND_DEFAULT);
            $target = (clone $query)->where('version', $version)->firstOrFail();
            (clone $query)->update(['is_active' => false]);
            $target->update(['is_active' => true]);

            return $target;
        });
    }

    /** @return Collection<int, Prompt> */
    public function history(ModuleContract $module, string $key)
    {
        return Prompt::with('creator')->where('module_key', $module->key())->where('prompt_key', $key)
            ->where('kind', Prompt::KIND_DEFAULT)->orderByDesc('version')->get();
    }

    public function personal(User $user, ModuleContract $module, string $key): ?Prompt
    {
        return Prompt::where('module_key', $module->key())->where('prompt_key', $key)
            ->where('kind', Prompt::KIND_PERSONAL)->where('user_id', $user->id)->latest('id')->first();
    }

    public function savePersonal(User $user, ModuleContract $module, string $key, string $body): Prompt
    {
        return Prompt::updateOrCreate(
            ['module_key' => $module->key(), 'prompt_key' => $key, 'kind' => Prompt::KIND_PERSONAL, 'user_id' => $user->id],
            ['body' => $body, 'is_active' => true, 'created_by' => $user->id],
        );
    }

    public function deletePersonal(User $user, ModuleContract $module, string $key): void
    {
        Prompt::where('module_key', $module->key())->where('prompt_key', $key)
            ->where('kind', Prompt::KIND_PERSONAL)->where('user_id', $user->id)->delete();
    }

    /** Prompt awal untuk pengguna: pribadi bila ada, selain itu default. */
    public function initialBody(User $user, ModuleContract $module, string $key): string
    {
        return $this->personal($user, $module, $key)?->body ?? $this->defaultBody($module, $key);
    }

    /** Ganti {{variabel}} dengan nilainya. Variabel tak dikenal dibiarkan apa adanya. */
    public function render(string $body, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($vars) {
            $name = strtolower($m[1]);

            return array_key_exists($name, $vars) ? (string) ($vars[$name] ?? '') : $m[0];
        }, $body);
    }

    /** @return string[] nama variabel yang dipakai di prompt */
    public function usedVariables(string $body): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $body, $m);

        return array_values(array_unique(array_map('strtolower', $m[1])));
    }

    /**
     * @param  array<string, array{description: string, required?: bool}>  $definitions
     * @return array{missing: string[], unknown: string[]}
     */
    public function analyze(string $body, array $definitions): array
    {
        $used = $this->usedVariables($body);
        $required = array_keys(array_filter($definitions, fn ($d) => $d['required'] ?? false));

        return [
            'missing' => array_values(array_diff($required, $used)),
            'unknown' => array_values(array_diff($used, array_keys($definitions))),
        ];
    }

    /** @return array<string, array> definisi prompt modul (kosong bila modul tidak menyediakannya) */
    public function definitions(ModuleContract $module, $session = null): array
    {
        if ($module instanceof DescribesPrompts) {
            return $module->promptDefinitions($session);
        }

        return array_map(fn () => ['label' => 'Prompt', 'variables' => []], $module->defaultPrompts());
    }
}
