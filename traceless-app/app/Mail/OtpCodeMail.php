<?php

namespace App\Mail;

use App\Models\EmailOtp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $purpose,
        public int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match ($this->purpose) {
                EmailOtp::PURPOSE_REGISTER => 'Your TraceLess verification code',
                EmailOtp::PURPOSE_DELETE_ACCOUNT => 'Confirm deletion of your TraceLess account',
                EmailOtp::PURPOSE_CHANGE_EMAIL => 'Confirm your new TraceLess email',
                default => 'Your TraceLess sign-in code',
            },
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp-code',
            with: [
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
                'purposeLabel' => match ($this->purpose) {
                    EmailOtp::PURPOSE_REGISTER => 'finish creating your account',
                    EmailOtp::PURPOSE_DELETE_ACCOUNT => 'confirm permanent account deletion',
                    EmailOtp::PURPOSE_CHANGE_EMAIL => 'confirm your new email address',
                    default => 'sign in to your account',
                },
            ],
        );
    }
}
