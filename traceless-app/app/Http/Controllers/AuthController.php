<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EmailOtp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(protected OtpService $otp) {}

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->merge([
            'email' => $this->otp->normalizeEmail((string) $request->input('email')),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users,email',
            'terms' => 'accepted',
        ]);

        $email = $validated['email'];

        if ($wait = $this->otp->secondsUntilResend($email, EmailOtp::PURPOSE_REGISTER)) {
            return back()->withErrors([
                'email' => "Please wait {$wait} seconds before requesting another code.",
            ])->onlyInput('name', 'email');
        }

        $request->session()->put('otp_auth', [
            'email' => $email,
            'purpose' => EmailOtp::PURPOSE_REGISTER,
            'name' => strip_tags($validated['name']),
            'remember' => false,
        ]);

        $issued = $this->otp->issue(
            $email,
            EmailOtp::PURPOSE_REGISTER,
            $request->ip(),
            $request->userAgent(),
        );

        $this->rememberDemoCode($request, $issued);

        AuditLog::create([
            'user_id' => null,
            'action' => 'otp_requested',
            'details' => 'Registration verification code requested.',
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('auth.verify')->with(
            'status',
            'We sent a 6-digit code to your email. It expires in '.config('traceless.otp.expiry_minutes', 10).' minutes.',
        );
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'remember' => 'sometimes|boolean',
        ]);

        $email = $this->otp->normalizeEmail($validated['email']);
        $user = User::where('email', $email)->first();

        $request->session()->put('otp_auth', [
            'email' => $email,
            'purpose' => EmailOtp::PURPOSE_LOGIN,
            'name' => null,
            'remember' => $request->boolean('remember'),
        ]);

        // Only issue a code for a real account. The response is identical
        // either way so the form cannot be used to enumerate accounts.
        if ($user) {
            $wait = $this->otp->secondsUntilResend($email, EmailOtp::PURPOSE_LOGIN);

            if (! $wait) {
                $issued = $this->otp->issue(
                    $email,
                    EmailOtp::PURPOSE_LOGIN,
                    $request->ip(),
                    $request->userAgent(),
                );

                $this->rememberDemoCode($request, $issued);

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'otp_requested',
                    'details' => 'Sign-in verification code requested.',
                    'ip_address' => $request->ip(),
                ]);
            }
        }

        return redirect()->route('auth.verify')->with(
            'status',
            'If that email has a TraceLess account, a sign-in code is on its way.',
        );
    }

    public function showVerify(Request $request)
    {
        $pending = $request->session()->get('otp_auth');

        if (! $pending) {
            return redirect()->route('login')->with(
                'status',
                'Request a sign-in code to continue.',
            );
        }

        return view('auth.verify', [
            'email' => $pending['email'],
            'maskedEmail' => $this->maskEmail($pending['email']),
            'purpose' => $pending['purpose'],
            'isRegistration' => $pending['purpose'] === EmailOtp::PURPOSE_REGISTER,
            'demoCode' => $this->demoCode($request),
            'resendAfter' => $this->otp->secondsUntilResend($pending['email'], $pending['purpose']),
        ]);
    }

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|digits:6',
        ]);

        $pending = $request->session()->get('otp_auth');

        if (! $pending) {
            return redirect()->route('login')->with(
                'status',
                'Your verification session expired. Request a new code.',
            );
        }

        $result = $this->otp->verify($pending['email'], $pending['purpose'], $validated['code']);

        if (! $result['ok']) {
            AuditLog::create([
                'user_id' => User::where('email', $pending['email'])->value('id'),
                'action' => 'otp_failed',
                'details' => 'Rejected verification code ('.$result['reason'].').',
                'ip_address' => $request->ip(),
            ]);

            return back()->withErrors([
                'code' => match ($result['reason']) {
                    'expired' => 'That code has expired. Request a new one.',
                    'locked' => 'Too many incorrect attempts. Request a new code.',
                    default => 'That code is not valid. Check the email and try again.',
                },
            ]);
        }

        if ($pending['purpose'] === EmailOtp::PURPOSE_REGISTER) {
            $user = $this->completeRegistration($request, $pending);
        } else {
            $user = User::where('email', $pending['email'])->first();

            if (! $user) {
                $request->session()->forget(['otp_auth', 'otp_demo_code']);

                return redirect()->route('login')->withErrors([
                    'email' => 'That code is not valid. Request a new one.',
                ]);
            }
        }

        Auth::login($user, (bool) ($pending['remember'] ?? false));
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => $pending['purpose'] === EmailOtp::PURPOSE_REGISTER ? 'account_created' : 'login',
            'details' => $pending['purpose'] === EmailOtp::PURPOSE_REGISTER
                ? 'Account created after email verification.'
                : 'User authenticated with an email one-time code.',
            'ip_address' => $request->ip(),
        ]);

        $request->session()->forget(['otp_auth', 'otp_demo_code']);

        if ($pending['purpose'] === EmailOtp::PURPOSE_REGISTER) {
            return redirect()->route('identity.create');
        }

        return redirect()->intended('/dashboard');
    }

    public function resend(Request $request)
    {
        $pending = $request->session()->get('otp_auth');

        if (! $pending) {
            return redirect()->route('login')->with(
                'status',
                'Your verification session expired. Request a new code.',
            );
        }

        if ($wait = $this->otp->secondsUntilResend($pending['email'], $pending['purpose'])) {
            return back()->withErrors([
                'code' => "Please wait {$wait} seconds before requesting another code.",
            ]);
        }

        $user = User::where('email', $pending['email'])->first();

        // Never reveal whether an account exists for a login challenge.
        if ($pending['purpose'] !== EmailOtp::PURPOSE_LOGIN || $user) {
            $issued = $this->otp->issue(
                $pending['email'],
                $pending['purpose'],
                $request->ip(),
                $request->userAgent(),
            );

            $this->rememberDemoCode($request, $issued);

            AuditLog::create([
                'user_id' => $user?->id,
                'action' => 'otp_resent',
                'details' => 'Verification code reissued.',
                'ip_address' => $request->ip(),
            ]);
        }

        return back()->with(
            'status',
            'If that email has a TraceLess account, a fresh code is on its way.',
        );
    }

    public function logout(Request $request)
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'logout',
            'ip_address' => $request->ip(),
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * @param  array{email: string, purpose: string, name: ?string, remember: bool}  $pending
     */
    protected function completeRegistration(Request $request, array $pending): User
    {
        $user = User::create([
            'name' => $pending['name'] ?: Str::before($pending['email'], '@'),
            'email' => $pending['email'],
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        event(new Registered($user));

        return $user;
    }

    protected function rememberDemoCode(Request $request, EmailOtp $otp): void
    {
        if (config('traceless.otp.demo_display') && $otp->plainCode) {
            $request->session()->put('otp_demo_code', $otp->plainCode);
        }
    }

    protected function demoCode(Request $request): ?string
    {
        if (! config('traceless.otp.demo_display')) {
            return null;
        }

        return $request->session()->get('otp_demo_code');
    }

    protected function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 1).str_repeat('•', max(3, mb_strlen($name) - 1)).'@'.$domain;
    }
}
