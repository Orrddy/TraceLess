<?php

namespace App\Models;

use App\Enums\ScanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends Model
{
    use HasFactory;

    protected $fillable = [
        'identity_profile_id',
        'type',
        'status',
        'progress_percentage',
        'current_stage',
        'demo_mode',
        'error_message',
        'sources_checked',
        'sources_total',
        'findings_count',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'demo_mode' => 'boolean',
        'progress_percentage' => 'integer',
        'sources_checked' => 'integer',
        'sources_total' => 'integer',
        'findings_count' => 'integer',
    ];

    public function identityProfile(): BelongsTo
    {
        return $this->belongsTo(IdentityProfile::class);
    }

    public function scanSources(): HasMany
    {
        return $this->hasMany(ScanSource::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function isActive(): bool
    {
        return ScanStatus::tryFrom($this->status)?->isActive() ?? false;
    }

    public function statusLabel(): string
    {
        return ScanStatus::tryFrom($this->status)?->label() ?? ucfirst($this->status);
    }

    public function durationForHumans(): string
    {
        if (! $this->started_at) {
            return '—';
        }

        $end = $this->completed_at ?? now();

        return $this->started_at->diffForHumans($end, true);
    }
}
