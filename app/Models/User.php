<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'google_id',
        'avatar_url',
        'prodi_id',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'status' => AccountStatus::class,
            'status_until' => 'datetime',
            'prodi_locked' => 'boolean',
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Status efektif: status bertanggal berakhir otomatis kembali `aktif`
     * setelah lewat tanggalnya.
     */
    public function effectiveStatus(): AccountStatus
    {
        $status = $this->status ?? AccountStatus::Aktif;

        if ($status !== AccountStatus::Aktif && $this->status_until && $this->status_until->isPast()) {
            return AccountStatus::Aktif;
        }

        return $status;
    }

    public function canLogin(): bool
    {
        return $this->effectiveStatus()->canLogin();
    }

    public function canGenerate(): bool
    {
        return $this->effectiveStatus()->canGenerate();
    }
}
