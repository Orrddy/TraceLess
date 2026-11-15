<?php

namespace App\Support;

use RuntimeException;

/**
 * Refuses to start a production deployment that is configured with development
 * conveniences.
 *
 * Each setting below is safe locally and dangerous in production:
 *
 * - APP_DEBUG=true renders stack traces, environment values and query bindings
 *   into HTTP responses and logs.
 * - TRACELESS_DEMO_MODE=true makes the scanner return fabricated findings
 *   instead of checking public sources.
 * - TRACELESS_OTP_DEMO_DISPLAY=true renders the six-digit sign-in code on the
 *   verification screen. Because a code is issued for any existing account,
 *   that is an account-takeover path, not a convenience.
 *
 * Failing at boot is deliberate: the alternative is a running service that
 * silently misrepresents its data or hands out sessions.
 */
class ProductionSafetyGuard
{
    /**
     * Human-readable reason for each unsafe setting.
     *
     * @var array<string, string>
     */
    private const REASONS = [
        'debug' => 'APP_DEBUG must be false in production; stack traces and configuration values leak.',
        'demo_mode' => 'TRACELESS_DEMO_MODE must be false in production; demo mode fabricates findings.',
        'otp_demo_display' => 'TRACELESS_OTP_DEMO_DISPLAY must be false in production; it renders sign-in codes on screen.',
    ];

    /**
     * Settings that are unsafe for a production deployment.
     *
     * Pure function so the rules can be tested without booting a production app.
     *
     * @param  array{production?: bool, debug?: bool, demo_mode?: bool, otp_demo_display?: bool}  $settings
     * @return array<int, string>
     */
    public static function violations(array $settings): array
    {
        if (! ($settings['production'] ?? false)) {
            return [];
        }

        $violations = [];

        foreach (self::REASONS as $key => $reason) {
            if (self::isEnabled($settings[$key] ?? false)) {
                $violations[] = $reason;
            }
        }

        return $violations;
    }

    /**
     * Throw when the current configuration is unsafe for the current environment.
     *
     * @throws RuntimeException
     */
    public function enforce(): void
    {
        $violations = self::violations([
            'production' => app()->environment('production'),
            'debug' => config('app.debug'),
            'demo_mode' => config('traceless.demo_mode'),
            'otp_demo_display' => config('traceless.otp.demo_display'),
        ]);

        if ($violations !== []) {
            throw new RuntimeException(
                "TraceLess refused to start: unsafe production configuration.\n - "
                .implode("\n - ", $violations)
                ."\nSet the matching environment variables in .env (see .env.staging.example)."
            );
        }
    }

    /**
     * Treat Laravel's string forms of "off" as disabled, so a value such as
     * "false" read through a config cache cannot be mistaken for "on".
     */
    private static function isEnabled(mixed $value): bool
    {
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) $value;
    }
}
