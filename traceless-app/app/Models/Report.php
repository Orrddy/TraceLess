<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'identity_profile_id',
        'title',
        'report_type',
        'privacy_score',
        'total_findings',
        'summary',
        'generated_at',
        'download_count',
        'signature',
        'demo_mode',
        'download_token',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'demo_mode' => 'boolean',
        'download_count' => 'integer',
        'privacy_score' => 'integer',
        'total_findings' => 'integer',
    ];

    public function identityProfile(): BelongsTo
    {
        return $this->belongsTo(IdentityProfile::class);
    }

    public function downloadUrl(): string
    {
        return route('reports.download', ['report' => $this->id, 'token' => $this->download_token]);
    }
}
