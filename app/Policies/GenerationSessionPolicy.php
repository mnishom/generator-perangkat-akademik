<?php

namespace App\Policies;

use App\Models\GenerationSession;
use App\Models\User;

class GenerationSessionPolicy
{
    /** Pemilik, atau admin (hanya baca; aksesnya dicatat di audit log). */
    public function view(User $user, GenerationSession $session): bool
    {
        return $user->id === $session->user_id || $user->isAdmin();
    }

    /** Mengubah, menjalankan generate, membatalkan: hanya pemilik. */
    public function update(User $user, GenerationSession $session): bool
    {
        return $user->id === $session->user_id;
    }
}
