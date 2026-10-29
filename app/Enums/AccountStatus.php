<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Aktif = 'aktif';
    case Ditangguhkan = 'ditangguhkan';
    case Dinonaktifkan = 'dinonaktifkan';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Ditangguhkan => 'Ditangguhkan',
            self::Dinonaktifkan => 'Dinonaktifkan',
        };
    }

    public function canLogin(): bool
    {
        return $this !== self::Dinonaktifkan;
    }

    public function canGenerate(): bool
    {
        return $this === self::Aktif;
    }
}
