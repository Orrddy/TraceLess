<?php

namespace App\Policies;

use App\Models\IdentityProfile;
use App\Models\User;

class IdentityProfilePolicy
{
    public function view(User $user, IdentityProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function update(User $user, IdentityProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function delete(User $user, IdentityProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }
}
