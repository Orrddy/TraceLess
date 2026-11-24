<?php

namespace App\Mail;

use App\Models\Scan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeepScanStartedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Scan $scan,
        public int $estimatedSeconds,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your TraceLess deep scan has started',
        );
    }

    public function content(): Content
    {
        $profile = $this->scan->identityProfile;

        return new Content(
            view: 'emails.deep-scan-started',
            with: [
                'scan' => $this->scan,
                'identityName' => $profile?->full_name ?: 'your identity',
                'estimatedHours' => max(1, (int) ceil($this->estimatedSeconds / 3600)),
                'statusUrl' => route('scans.show', $this->scan),
                'logoUrl' => config('mail.logo_url') ?: asset('apple-touch-icon.png'),
                'wordmarkUrl' => config('mail.wordmark_url') ?: asset('brand/wordmark-email.png'),
            ],
        );
    }
}
