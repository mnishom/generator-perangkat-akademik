<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Status global satu modul (tabel `modules`).
 */
class ModuleState extends Model
{
    protected $table = 'modules';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'enabled', 'generate_open', 'model', 'settings'];

    protected $attributes = ['enabled' => true, 'generate_open' => true];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'generate_open' => 'boolean', 'settings' => 'array'];
    }

    public static function for(string $key): self
    {
        return static::firstOrCreate(['key' => $key]);
    }
}
