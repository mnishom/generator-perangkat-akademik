<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Number;

class LibraryFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'prodi_id', 'uploaded_by', 'role', 'module_key', 'is_shared', 'original_name', 'path',
        'extension', 'mime', 'size', 'sha256', 'description',
    ];

    protected function casts(): array
    {
        return ['is_shared' => 'boolean'];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Berkas yang dapat dipakai pengguna: milik prodinya + berkas bersama. */
    public function scopeAvailableTo(Builder $q, User $user): Builder
    {
        return $q->where(fn ($q) => $q->where('is_shared', true)
            ->when($user->prodi_id, fn ($q) => $q->orWhere('prodi_id', $user->prodi_id)));
    }

    public function absolutePath(): string
    {
        return \Storage::disk('local')->path($this->path);
    }

    public function sizeForHumans(): string
    {
        return Number::fileSize($this->size, 1);
    }
}
