<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\ConsentRecord;
use App\Models\EmailOtp;
use App\Models\OutboundEmail;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SettingsPage extends Component
{
    public string $name = '';

    public string $email = '';

    public ?string $pending_email = null;

    public string $email_change_code = '';

    public bool $email_change_sent = false;

    public ?string $email_change_demo_code = null;

    public string $delete_code = '';

    public bool $delete_code_sent = false;

    public ?string $delete_demo_code = null;

    public bool $confirm_data_purge = false;

    public bool $confirm_account_delete = false;

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfile(): void
    {
        $user = Auth::user();
        $this->email = app(OtpService::class)->normalizeEmail($this->email);

        $this->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update(['name' => strip_tags($this->name)]);

        if ($user->email === $this->email) {
            app(AuditService::class)->log($user->id, 'profile_updated', 'Account profile updated.', request()->ip());
            $this->dispatch('notify', message: 'Profile updated.');

            return;
        }

        $otp = app(OtpService::class);

        if (! $this->allowOtpRequest('email-change', 'email')) {
            return;
        }

        if ($wait = $otp->secondsUntilResend($this->email, EmailOtp::PURPOSE_CHANGE_EMAIL)) {
            $this->addError('email', "Please wait {$wait} seconds before requesting another code.");

            return;
        }

        $issued = null;

        try {
            $issued = $otp->issue(
                $this->email,
                EmailOtp::PURPOSE_CHANGE_EMAIL,
                request()->ip(),
                request()->userAgent(),
            );
        } catch (\Throwable) {
            $this->addError('email', 'We could not send the verification email just now. Please try again in a minute.');

            return;
        }

        $this->pending_email = $this->email;
        $this->email_change_sent = true;
        $this->email_change_code = '';
        $this->email_change_demo_code = config('traceless.otp.demo_display') ? $issued->plainCode : null;

        app(AuditService::class)->log($user->id, 'email_change_requested', 'Verification code sent to a new email address.', request()->ip());
        $this->dispatch('notify', message: 'Enter the 6-digit code sent to your new address to finish the change.');
    }

    public function confirmEmailChange(): void
    {
        $this->validate(['email_change_code' => 'required|digits:6']);

        $user = Auth::user();

        if (! $this->pending_email) {
            $this->addError('email_change_code', 'Request a new verification code first.');

            return;
        }

        if (User::where('email', $this->pending_email)->whereKeyNot($user->id)->exists()) {
            $this->addError('email', 'That email address is already in use.');

            return;
        }

        $result = app(OtpService::class)->verify(
            $this->pending_email,
            EmailOtp::PURPOSE_CHANGE_EMAIL,
            $this->email_change_code,
        );

        if (! $result['ok']) {
            $this->addError('email_change_code', match ($result['reason']) {
                'expired' => 'That code has expired. Request a new one.',
                'locked' => 'Too many incorrect attempts. Request a new code.',
                default => 'That code is not valid. Check the email and try again.',
            });

            return;
        }

        $oldEmail = $user->email;
        $newEmail = $this->pending_email;

        $user->forceFill([
            'email' => $newEmail,
            'email_verified_at' => now(),
        ])->save();

        $this->email = $newEmail;
        $this->pending_email = null;
        $this->email_change_code = '';
        $this->email_change_sent = false;
        $this->email_change_demo_code = null;

        app(AuditService::class)->log(
            $user->id,
            'email_changed',
            "Sign-in email changed from {$oldEmail} to {$newEmail}.",
            request()->ip(),
        );
        $this->dispatch('notify', message: 'Email updated and verified. Use it for future sign-in codes.');
    }

    public function cancelEmailChange(): void
    {
        $this->pending_email = null;
        $this->email_change_code = '';
        $this->email_change_sent = false;
        $this->email_change_demo_code = null;
        $this->resetErrorBag();
    }

    public function requestDeleteCode(): void
    {
        $user = Auth::user();
        $otp = app(OtpService::class);

        if (! $this->allowOtpRequest('delete-account', 'delete_code')) {
            return;
        }

        if ($wait = $otp->secondsUntilResend($user->email, EmailOtp::PURPOSE_DELETE_ACCOUNT)) {
            $this->addError('delete_code', "Please wait {$wait} seconds before requesting another code.");

            return;
        }

        $issued = null;

        try {
            $issued = $otp->issue(
                $user->email,
                EmailOtp::PURPOSE_DELETE_ACCOUNT,
                request()->ip(),
                request()->userAgent(),
            );
        } catch (\Throwable) {
            $this->addError('delete_code', 'We could not send the deletion code just now. Please try again in a minute.');

            return;
        }

        $this->delete_code_sent = true;
        $this->delete_code = '';
        $this->delete_demo_code = config('traceless.otp.demo_display') ? $issued->plainCode : null;

        app(AuditService::class)->log($user->id, 'account_delete_requested', 'Deletion verification code requested.', request()->ip());
        $this->dispatch('notify', message: 'Enter the 6-digit code sent to your email to confirm deletion.');
    }

    public function purgeScanData(): void
    {
        $this->validate(['confirm_data_purge' => 'accepted']);
        $user = Auth::user();

        foreach ($user->identityProfiles as $profile) {
            $profile->reports()->delete();
            $profile->alerts()->delete();
            $profile->removalTasks()->delete();
            $profile->credentialExposures()->delete();
            $profile->findings()->delete();
            $profile->scans()->delete();
            $profile->monitoringSubscription?->delete();
        }

        // The database notification channel keeps its own copy of the alert text.
        $user->notifications()->delete();

        $this->confirm_data_purge = false;
        app(AuditService::class)->log($user->id, 'scan_data_purged', 'User purged all scan findings and reports.', request()->ip());
        $this->dispatch('notify', message: 'All scan findings, reports and removal records were deleted.');
    }

    public function deleteAccount()
    {
        $this->validate([
            'delete_code' => 'required|digits:6',
            'confirm_account_delete' => 'accepted',
        ]);

        $user = Auth::user();

        $result = app(OtpService::class)->verify(
            $user->email,
            EmailOtp::PURPOSE_DELETE_ACCOUNT,
            $this->delete_code,
        );

        if (! $result['ok']) {
            $this->addError('delete_code', match ($result['reason']) {
                'expired' => 'That code has expired. Request a new one.',
                'locked' => 'Too many incorrect attempts. Request a new code.',
                default => 'That code is not valid. Check the email and try again.',
            });

            return;
        }

        $userId = $user->id;

        app(AuditService::class)->log($userId, 'account_deleted', 'User requested account deletion.', request()->ip());

        // Consent records cascade with the profile, but TraceLess must be able to
        // prove consent was recorded. Copy the evidence into the audit trail
        // (which survives the account) before anything is deleted.
        foreach (ConsentRecord::where('user_id', $userId)->get() as $consent) {
            AuditLog::create([
                'user_id' => null,
                'action' => 'consent_evidence_retained',
                'details' => 'Consent on record for profile #'.$consent->identity_profile_id
                    .' agreed at '.$consent->agreed_at?->toIso8601String()
                    .': '.$consent->consent_text,
                'ip_address' => null,
            ]);
        }

        // Personal data does not outlive the account: strip IP addresses from the
        // audit rows that remain, keeping only the action and its timestamp.
        AuditLog::where('user_id', $userId)->update(['ip_address' => null]);

        EmailOtp::where('email', $user->email)->delete();
        $user->notifications()->delete();

        // Delivery telemetry can contain the address itself, so it must not
        // outlive the account it belongs to.
        OutboundEmail::where('user_id', $userId)
            ->orWhere(fn ($query) => $query->whereNull('user_id')->where('to_email', $user->email))
            ->delete();

        // Log out before deleting the model. SessionGuard::logout cycles the
        // remember token, which would re-insert a model already marked deleted.
        Auth::logout();
        $user->delete();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        return redirect('/')->with('status', 'Your account and associated data have been deleted.');
    }

    /**
     * Account-change codes are limited per user, independently of the per-address
     * resend cooldown, so the form cannot be used to spray a mailbox.
     */
    private function allowOtpRequest(string $bucket, string $errorKey): bool
    {
        $key = 'settings-otp:'.$bucket.':'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError($errorKey, 'Too many code requests. Try again in '.RateLimiter::availableIn($key).' seconds.');

            return false;
        }

        RateLimiter::hit($key, 600);

        return true;
    }

    public function render()
    {
        return view('livewire.settings-page', [
            'user' => Auth::user(),
            'auditLogs' => Auth::user()->auditLogs()->latest()->take(10)->get(),
            'subscription' => Auth::user()->activeSubscription(),
            'profile' => Auth::user()->latestProfile(),
        ])->layout('layouts.app', ['title' => 'Account settings — TraceLess']);
    }
}
