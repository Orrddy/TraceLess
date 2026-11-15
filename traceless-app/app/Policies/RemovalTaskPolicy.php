<?php

namespace App\Policies;

use App\Models\RemovalTask;
use App\Models\User;

class RemovalTaskPolicy
{
    public function view(User $user, RemovalTask $task): bool
    {
        return $task->identityProfile()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, RemovalTask $task): bool
    {
        return $this->view($user, $task);
    }
}
