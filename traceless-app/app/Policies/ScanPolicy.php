<?php

namespace App\Policies;

use App\Models\Scan;
use App\Models\User;

class ScanPolicy
{
    public function view(User $user, Scan $scan): bool
    {
        return $scan->identityProfile()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Scan $scan): bool
    {
        return $this->view($user, $scan);
    }

    public function cancel(User $user, Scan $scan): bool
    {
        return $this->view($user, $scan) && $scan->isActive();
    }
}
