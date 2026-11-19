<?php

namespace App\Services;

use App\Models\OutboundEmail;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records outbound email so the operations panel can show delivery health.
 *
 * Successes are captured from Laravel's MessageSent event. Transport failures
 * throw before that event fires, so call sites that must not fail silently (the
 * sign-in code) report through recordFailed().
 */
class OutboundEmailRecorder
{
    public function recordSent(MessageSent $event): void
    {
        try {
            $message = $event->message;
            $to = method_exists($message, 'getTo') ? ($message->getTo()[0] ?? null) : null;
            $address = $to?->getAddress();

            if (! $address) {
                return;
            }

            $this->store([
                'to_email' => $address,
                'subject' => $this->subject($message),
                'status' => 'sent',
                'provider_message_id' => $event->sent?->getMessageId(),
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            // Observability must never break a mail send.
            Log::warning('outbound_email_record_failed', ['reason' => get_class($exception)]);
        }
    }

    public function recordFailed(string $toEmail, ?string $subject, Throwable $exception): void
    {
        try {
            $this->store([
                'to_email' => $toEmail,
                'subject' => $subject,
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
        } catch (Throwable $inner) {
            Log::warning('outbound_email_record_failed', ['reason' => get_class($inner)]);
        }
    }

    /**
     * @param  array{to_email: string, subject?: ?string, status: string, provider_message_id?: ?string, error?: ?string, sent_at?: mixed}  $attributes
     */
    private function store(array $attributes): void
    {
        $attributes['user_id'] = User::where('email', $attributes['to_email'])->value('id');

        OutboundEmail::create($attributes);
    }

    private function subject(object $message): ?string
    {
        try {
            return method_exists($message, 'getSubject') ? mb_substr((string) $message->getSubject(), 0, 255) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
