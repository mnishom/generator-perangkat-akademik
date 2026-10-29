<?php

namespace App\Policies;

use App\Models\LibraryFile;
use App\Models\User;

class LibraryFilePolicy
{
    public function view(User $user, LibraryFile $file): bool
    {
        return $user->isAdmin() || $file->is_shared || ($file->prodi_id && $file->prodi_id === $user->prodi_id);
    }

    /** Hapus: pengunggah, atau admin. Berkas bersama hanya admin. */
    public function delete(User $user, LibraryFile $file): bool
    {
        if ($file->is_shared) {
            return $user->isAdmin();
        }

        return $user->isAdmin() || $file->uploaded_by === $user->id;
    }
}
