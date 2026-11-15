<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicIdentifier extends Model
{
    protected $fillable = [
        'identity_profile_id',
        'type',
        'value',
        'is_monitored',
    ];

    protected $casts = [
        'is_monitored' => 'boolean',
    ];

    public function identityProfile(): BelongsTo
    {
        return $this->belongsTo(IdentityProfile::class);
    }
}
