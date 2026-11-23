<?php

namespace App\Notifications;

use App\Models\Scan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScanCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Scan $scan) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
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

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your TraceLess scan is complete')
            ->line("Scan #{$this->scan->id} finished with {$this->scan->findings_count} public findings.")
            ->line('Review the evidence before starting any removal request.')
            ->action('Open TraceLess', route('scans.show', $this->scan));
    }
}
