<?php

namespace App\Services;

use App\Mail\OtpCodeMail;
use App\Models\EmailOtp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Passwordless email one-time codes.
 *
 * Codes are six digits, stored only as a hash, expire after a short window,
 * are single-use, and lock after a fixed number of wrong attempts. The demo
 * code is returned to the caller only so local/demo mode can display it.
 */
class OtpService
{
    public function issue(
        string $email,
        string $purpose = EmailOtp::PURPOSE_LOGIN,
        ?string $ip = null,
        ?string $userAgent = null,
    ): EmailOtp {
        $email = $this->normalizeEmail($email);

        // Only the newest code may be valid.
        EmailOtp::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = $this->generateCode();

        $otp = EmailOtp::create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes((int) config('traceless.otp.expiry_minutes', 10)),
            'ip_address' => $ip,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
        ]);

        try {
            Mail::to($email)->send(new OtpCodeMail(
                code: $code,
                purpose: $purpose,
                expiresInMinutes: (int) config('traceless.otp.expiry_minutes', 10),
            ));
        } catch (\Throwable $exception) {
            // Sign-in codes must never fail silently. Record the transport error
            // for the operations panel, then let the caller surface the failure.
            app(OutboundEmailRecorder::class)->recordFailed($email, 'TraceLess sign-in code', $exception);

            throw $exception;
        }

        // Transient value for demo display and tests. Never persisted.
        $otp->plainCode = $code;

        return $otp;
    }

    /**
     * @return array{ok: bool, reason: string, otp: ?EmailOtp}
     */
    public function verify(string $email, string $purpose, string $code): array
    {
        $email = $this->normalizeEmail($email);

        $otp = EmailOtp::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            return ['ok' => false, 'reason' => 'invalid', 'otp' => null];
        }

        if ($otp->isLocked()) {
            return ['ok' => false, 'reason' => 'locked', 'otp' => $otp];
        }

        if ($otp->isExpired()) {
            return ['ok' => false, 'reason' => 'expired', 'otp' => $otp];
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            $otp->refresh();

            return [
                'ok' => false,
                'reason' => $otp->isLocked() ? 'locked' : 'invalid',
                'otp' => $otp,
            ];
        }

        $otp->update(['consumed_at' => now()]);

        return ['ok' => true, 'reason' => 'verified', 'otp' => $otp];
    }

    public function secondsUntilResend(
        string $email,
        string $purpose = EmailOtp::PURPOSE_LOGIN,
    ): int {
        $latest = EmailOtp::query()
            ->where('email', $this->normalizeEmail($email))
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (! $latest) {
            return 0;
        }

        $cooldown = (int) config('traceless.otp.resend_cooldown_seconds', 60);
        $elapsed = $latest->created_at->diffInSeconds(now());

        return max(0, $cooldown - $elapsed);
    }

    public function purgeExpired(int $keepDays = 1): int
    {
        return EmailOtp::query()
            ->where('expires_at', '<', now()->subDays($keepDays))
            ->delete();
    }

    public function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    protected function generateCode(): string
    {
        $digits = max(4, min(10, (int) config('traceless.otp.digits', 6)));
        $max = (10 ** $digits) - 1;

        return str_pad((string) random_int(0, $max), $digits, '0', STR_PAD_LEFT);
    }
}
