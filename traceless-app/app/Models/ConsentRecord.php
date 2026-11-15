<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentRecord extends Model
{
    protected $fillable = [
        'user_id',
        'identity_profile_id',
        'consent_text',
        'ip_address',
        'user_agent',
        'agreed_at',
    ];

    protected $casts = [
        'agreed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function identityProfile(): BelongsTo
    {
        return $this->belongsTo(IdentityProfile::class);
    }
}
