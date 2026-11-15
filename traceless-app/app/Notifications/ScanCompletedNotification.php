<?php

namespace App\Notifications;

use App\Models\Scan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ScanCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(public Scan $scan) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $profile = $this->scan->identityProfile;

        return [
            'type' => 'scan_completed',
            'scan_id' => $this->scan->id,
            'title' => 'Scan complete',
            'message' => "{$this->scan->findings_count} public findings indexed for {$profile->full_name}. Privacy score: {$profile->privacy_score}/100.",
            'url' => route('scans.show', $this->scan),
            'severity' => 'low',
        ];
    }
}
