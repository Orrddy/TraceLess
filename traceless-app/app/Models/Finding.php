<?php

namespace App\Models;

use App\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Finding extends Model
{
    use HasFactory;

    protected $fillable = [
        'identity_profile_id',
        'scan_id',
        'source',
        'category',
        'identifier_type',
        'identifier_value',
        'url',
        'confidence',
        'risk',
        'removal_method',
        'status',
        'verification_status',
        'evidence',
        'http_status',
        'verified_at',
        'title',
        'description',
        'action_guidance',
        'discovered_at',
        'last_checked_at',
    ];

    protected $casts = [
        'confidence' => 'float',
        'evidence' => 'array',
        'http_status' => 'integer',
        'verified_at' => 'datetime',
        'discovered_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function identityProfile(): BelongsTo
    {
        return $this->belongsTo(IdentityProfile::class);
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function removalTask(): HasOne
    {
        return $this->hasOne(RemovalTask::class);
    }

    public function riskEnum(): RiskLevel
    {
        return RiskLevel::tryFrom($this->risk) ?? RiskLevel::Low;
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->whereHas('identityProfile', fn (Builder $q) => $q->where('user_id', $userId));
    }

    public function scopeHighRisk(Builder $query): Builder
    {
        return $query->whereIn('risk', [RiskLevel::High->value, RiskLevel::Critical->value]);
    }
}
