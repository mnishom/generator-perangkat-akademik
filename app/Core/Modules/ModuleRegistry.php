<?php

namespace App\Core\Modules;

use InvalidArgumentException;

/**
 * Daftar modul yang terdaftar di config/modules.php.
 */
class ModuleRegistry
{
    /** @var array<string, ModuleContract>|null */
    private ?array $modules = null;

    /** @return array<string, ModuleContract> */
    public function all(): array
    {
        if ($this->modules === null) {
            $this->modules = [];
            foreach (config('modules.modules', []) as $class) {
                $module = app($class);
                if (! $module instanceof ModuleContract) {
                    throw new InvalidArgumentException("{$class} harus mengimplementasikan ModuleContract.");
                }
                $this->modules[$module->key()] = $module;
            }
        }

        return $this->modules;
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    public function get(string $key): ModuleContract
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Modul [{$key}] tidak terdaftar.");
    }

    /** Untuk test: daftarkan ulang setelah config diubah. */
    public function flush(): void
    {
        $this->modules = null;
    }
}
