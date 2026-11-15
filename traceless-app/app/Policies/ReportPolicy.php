<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function view(User $user, Report $report): bool
    {
        return $report->identityProfile()->where('user_id', $user->id)->exists();
    }

    public function download(User $user, Report $report): bool
    {
        return $this->view($user, $report);
    }
}
