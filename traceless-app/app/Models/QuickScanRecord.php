<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickScanRecord extends Model
{
    protected $fillable = [
        'name',
        'email',
        'username',
        'sources',
        'green_sources',
        'checked_count',
        'public_matches',
        'username_matches',
        'email_matches',
        'ip_hash',
    ];

    protected $casts = [
        'sources' => 'array',
        'green_sources' => 'array',
        'checked_count' => 'integer',
        'public_matches' => 'integer',
        'username_matches' => 'integer',
        'email_matches' => 'integer',
    ];

    public function greenSourcesLabel(): string
    {
        return $this->green_sources ? implode(', ', $this->green_sources) : '—';
    }
}
