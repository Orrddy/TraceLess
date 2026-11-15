<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanSource extends Model
{
    protected $fillable = [
        'scan_id',
        'name',
        'category',
        'status',
        'outcome',
        'http_status',
        'checked_url',
        'outcome_reason',
        'findings_found',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'findings_found' => 'integer',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}
