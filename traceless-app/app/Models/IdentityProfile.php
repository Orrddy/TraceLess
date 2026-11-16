<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class IdentityProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'full_name',
        'previous_names',
        'aliases',
        'primary_email',
        'phone_number',
        'region',
        'consent_acknowledged',
        'consent_given_at',
        'privacy_score',
    ];

    protected $casts = [
        'consent_acknowledged' => 'boolean',
        'consent_given_at' => 'datetime',
        'privacy_score' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publicIdentifiers(): HasMany
    {
        return $this->hasMany(PublicIdentifier::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function credentialExposures(): HasMany
    {
        return $this->hasMany(CredentialExposure::class);
    }

    public function removalTasks(): HasMany
    {
        return $this->hasMany(RemovalTask::class);
    }

    public function monitoringSubscription(): HasOne
    {
        return $this->hasOne(MonitoringSubscription::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function consentRecords(): HasMany
    {
        return $this->hasMany(ConsentRecord::class);
    }

    public function latestScan(): ?Scan
    {
        return $this->scans()->latest()->first();
    }

    public function activeScan(): ?Scan
    {
        // A scan that stopped reporting is not "active"; treat it as gone so a
        // dead worker can never block the account from scanning again.
        return $this->scans()
            ->whereNotIn('status', ['completed', 'failed', 'cancelled'])
            ->where('updated_at', '>=', now()->subMinutes((int) config('traceless.scan.stale_minutes', 15)))
            ->latest()
            ->first();
    }
}
