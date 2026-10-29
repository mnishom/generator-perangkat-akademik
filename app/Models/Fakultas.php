<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fakultas extends Model
{
    use HasFactory;

    protected $table = 'fakultas';

    protected $fillable = ['nama', 'singkatan', 'nama_dekan'];

    public function prodis(): HasMany
    {
        return $this->hasMany(Prodi::class);
    }
}
