<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemovalTask extends Model
{
    protected $fillable = [
        'identity_profile_id',
        'finding_id',
        'source_name',
        'status',
        'workflow_type',
        'estimated_turnaround',
        'opt_out_url',
        'notes',
        'requested_at',
        'completed_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function identityProfile(): BelongsTo
    {
        return $this->belongsTo(IdentityProfile::class);
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }
}
