<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'identity_profile_id',
        'is_active',
        'cadence',
        'options',
        'last_scanned_at',
        'next_scan_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'options' => 'array',
        'last_scanned_at' => 'datetime',
        'next_scan_at' => 'datetime',
    ];

    public function identityProfile(): BelongsTo
    {
        return $this->belongsTo(IdentityProfile::class);
    }

    public function nextScanLabel(): string
    {
        return $this->next_scan_at?->diffForHumans() ?? 'Not scheduled';
    }
}
