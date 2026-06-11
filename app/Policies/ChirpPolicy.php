<?php

namespace App\Policies;

use App\Models\Chirp;
use App\Models\User;

class ChirpPolicy
{
    public function update(User $user, Chirp $chirp): bool
    {
        return $chirp->user_id === $user->id;
    }

    public function delete(User $user, Chirp $chirp): bool
    {
        return $chirp->user_id === $user->id;
    }
}
