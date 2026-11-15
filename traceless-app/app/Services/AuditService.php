<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    /**
     * Write a security audit event without storing sensitive target data.
     */
    public function log(?int $userId, string $action, ?string $details = null, ?string $ip = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'details' => $details ? $this->redact($details) : null,
            'ip_address' => $ip,
        ]);
    }

    protected function redact(string $value): string
    {
        // Redact email addresses and long digit strings from free-text logs.
        $value = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted-email]', $value);
        $value = preg_replace('/\b\d{10,}\b/', '[redacted-number]', $value);

        return mb_substr($value, 0, 1000);
    }
}
