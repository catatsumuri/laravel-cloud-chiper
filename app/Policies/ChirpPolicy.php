<?php

namespace App\Policies;

use App\Models\Chirp;
use App\Models\User;

class ChirpPolicy
{
    public function delete(User $user, Chirp $chirp): bool
    {
        return $chirp->user_id === $user->id;
    }
}
