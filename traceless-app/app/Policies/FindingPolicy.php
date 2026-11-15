<?php

namespace App\Policies;

use App\Models\Finding;
use App\Models\User;

class FindingPolicy
{
    public function view(User $user, Finding $finding): bool
    {
        return $finding->identityProfile()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Finding $finding): bool
    {
        return $this->view($user, $finding);
    }
}
