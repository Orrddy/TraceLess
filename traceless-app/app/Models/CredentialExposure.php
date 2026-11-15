<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialExposure extends Model
{
    protected $fillable = [
        'identity_profile_id',
        'email',
        'service_name',
        'breach_date',
        'exposed_data_classes',
        'risk_level',
        'is_resolved',
        'resolved_at',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function identityProfile(): BelongsTo
    {
        return $this->belongsTo(IdentityProfile::class);
    }
}
