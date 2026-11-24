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
            'reference' => $this->scan->reference(),
            'title' => 'Scan complete',
            'message' => "{$this->scan->findings_count} public findings indexed for {$profile->full_name}. Privacy score: {$profile->privacy_score}/100.",
            'url' => route('scans.show', $this->scan),
            'severity' => 'low',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $profile = $this->scan->identityProfile;

        return (new MailMessage)
            ->subject('Your TraceLess scan is complete')
            ->view('emails.scan-complete', [
                'scan' => $this->scan,
                'reference' => $this->scan->reference(),
                'identityName' => $profile?->full_name ?: 'Your identity',
                'findings' => (int) $this->scan->findings_count,
                'score' => (int) ($profile?->privacy_score ?? 0),
                'url' => route('scans.show', $this->scan),
                'logoUrl' => config('mail.logo_url') ?: asset('apple-touch-icon.png'),
                'wordmarkUrl' => config('mail.wordmark_url') ?: asset('brand/wordmark-email.png'),
            ]);
    }
}
