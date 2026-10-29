<?php

namespace App\Policies;

use App\Models\GeneratedDocument;
use App\Models\User;

class GeneratedDocumentPolicy
{
    public function view(User $user, GeneratedDocument $document): bool
    {
        return $user->id === $document->user_id || $user->isAdmin();
    }

    public function update(User $user, GeneratedDocument $document): bool
    {
        return $user->id === $document->user_id;
    }
}
