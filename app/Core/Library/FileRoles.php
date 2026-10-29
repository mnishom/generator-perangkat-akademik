<?php

namespace App\Core\Library;

use App\Core\Modules\InputRequirement;
use App\Core\Modules\ModuleRegistry;

/**
 * Daftar peran berkas pustaka, dikumpulkan dari inputRequirements() semua modul.
 */
class FileRoles
{
    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return array<string, array{label: string, mimes: string[], modules: string[], help: string}> */
    public function all(): array
    {
        $roles = [];
        foreach ($this->modules->all() as $module) {
            foreach ($module->inputRequirements() as $req) {
                if ($req->source !== InputRequirement::SOURCE_UPLOAD) {
                    continue;
                }
                $roles[$req->role] ??= ['label' => $req->label, 'mimes' => [], 'modules' => [], 'help' => $req->help];
                $roles[$req->role]['mimes'] = array_values(array_unique(array_merge($roles[$req->role]['mimes'], $req->mimes)));
                $roles[$req->role]['modules'][] = $module->name();
            }
        }

        return $roles;
    }

    public function label(string $role): string
    {
        return $this->all()[$role]['label'] ?? $role;
    }

    /** @return string[] */
    public function mimes(string $role): array
    {
        return $this->all()[$role]['mimes'] ?? ['docx', 'pdf'];
    }
}
