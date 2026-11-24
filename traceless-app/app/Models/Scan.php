<?php

namespace App\Models;

use App\Enums\ScanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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

    /**
     * Expected duration shown to the user. Deep scans are a full crawl; the
     * actual job usually finishes sooner, but the countdown must stay stable
     * across logout/login and multiple devices.
     */
    public function estimatedDurationSeconds(): int
    {
        return $this->type === 'deep_scan'
            ? (int) config('traceless.scan.deep_estimated_seconds', 7200)
            : (int) config('traceless.scan.snapshot_estimated_seconds', 120);
    }

    /**
     * Absolute completion estimate, derived from the persisted queue time.
     * Never relative to the client clock. For deep scans the full window is
     * always shown to the user, even when the crawl finishes earlier.
     */
    public function estimatedCompletionAt(): ?Carbon
    {
        $anchor = $this->created_at ?? $this->started_at;

        return $anchor?->copy()->addSeconds($this->estimatedDurationSeconds());
    }

    /**
     * When results become visible. A deep crawl can finish early; the product
     * still presents the full two-hour window instead of collapsing the progress
     * screen the moment the queue job ends.
     */
    public function resultsRevealAt(): ?Carbon
    {
        return $this->type === 'deep_scan' ? $this->estimatedCompletionAt() : null;
    }

    public function isHoldingResults(): bool
    {
        return $this->type === 'deep_scan'
            && $this->status === 'completed'
            && $this->resultsRevealAt()?->isFuture() === true;
    }

    public function isPresentedActive(): bool
    {
        return $this->isActive() || $this->isHoldingResults();
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
