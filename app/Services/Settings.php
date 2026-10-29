<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan sistem yang dapat diubah admin. Nilai disimpan sebagai JSON
 * di `app_settings` dan di-cache; default berasal dari self::DEFAULTS.
 */
class Settings
{
    public const DEFAULTS = [
        'generate_enabled' => true,
        'generate_closed_message' => 'Fitur generate sedang ditutup sementara oleh admin.',
        'max_upload_mb' => 20,
        'max_concurrent_jobs' => 2,
        'delete_remote_files' => true,
        'autofix_max' => 2,
        'default_model' => null,
    ];

    private const CACHE_KEY = 'app_settings.all';

    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => AppSetting::query()
            ->pluck('value', 'key')
            ->map(fn ($v) => json_decode((string) $v, true))
            ->all());

        return array_merge(self::DEFAULTS, $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        AppSetting::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        Cache::forget(self::CACHE_KEY);
    }

    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function generateEnabled(): bool
    {
        return (bool) $this->get('generate_enabled');
    }

    /** Model efektif: pilihan admin bila valid, selain itu dari .env. */
    public function defaultModel(): string
    {
        $model = $this->get('default_model');

        return is_string($model) && array_key_exists($model, config('claude.models'))
            ? $model
            : config('claude.model');
    }
}
