<?php

use App\Models\Scan;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('scans.{scanId}', function ($user, $scanId) {
    return Scan::whereKey($scanId)
        ->whereHas('identityProfile', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});
