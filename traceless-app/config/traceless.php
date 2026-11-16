<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Demo mode
    |--------------------------------------------------------------------------
    | When enabled, scans use the deterministic local demo adapter instead of
    | making outbound requests. Demo findings are always labelled as such in
    | the UI, reports and audit trail. Set TRACELESS_DEMO_MODE=false to use
    | the Python worker integration.
    */
    'demo_mode' => env('TRACELESS_DEMO_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | Python service boundary
    |--------------------------------------------------------------------------
    | Laravel never exposes these binaries to users. The orchestrator invokes
    | the worker with a fixed argument list and validates the JSON response.
    */
    'python' => [
        'binary' => env('TRACELESS_PYTHON_BINARY', 'python'),
        'scanner' => env('TRACELESS_SCANNER_SCRIPT', base_path('../python-services/scanner/worker.py')),
        'ai' => env('TRACELESS_AI_SCRIPT', base_path('../python-services/local-ai/safety_engine.py')),
        'timeout' => (int) env('TRACELESS_PYTHON_TIMEOUT', 90),
        'max_findings' => (int) env('TRACELESS_MAX_FINDINGS', 250),
    ],

    /*
    |--------------------------------------------------------------------------
    | Passwordless email OTP
    |--------------------------------------------------------------------------
    | TraceLess never stores account passwords. Sign-in, registration, email
    | changes and account deletion all use a short-lived emailed code. The
    | demo code is only rendered when demo mode is enabled.
    */
    'otp' => [
        'digits' => 6,
        'expiry_minutes' => (int) env('TRACELESS_OTP_EXPIRY_MINUTES', 10),
        'max_attempts' => (int) env('TRACELESS_OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('TRACELESS_OTP_RESEND_COOLDOWN', 60),
        'demo_display' => env('TRACELESS_OTP_DEMO_DISPLAY', env('TRACELESS_DEMO_MODE', true)),
    ],

    'scan' => [
        'sources_per_stage' => 4,
        'stage_delay_ms' => (int) env('TRACELESS_SCAN_DELAY_MS', 900),
        'minimum_score' => 5,
        // Live scans check every monitored username. Each one runs the worker
        // as a separate process, so the count bounds the wall-clock time.
        'max_usernames' => (int) env('TRACELESS_MAX_USERNAMES', 5),
        // A scan that has not been touched for this long is treated as dead.
        'stale_minutes' => (int) env('TRACELESS_SCAN_STALE_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Breach metadata provider
    |--------------------------------------------------------------------------
    | TraceLess never invents credential exposures. Until a breach provider is
    | configured, live scans skip this source and the UI says so explicitly.
    */
    'breach' => [
        'provider' => env('TRACELESS_BREACH_PROVIDER'),
    ],

    'retention' => [
        // Days after which raw scan telemetry and downloaded report files expire.
        'scan_telemetry_days' => (int) env('TRACELESS_RETENTION_SCAN_DAYS', 365),
        'report_days' => (int) env('TRACELESS_RETENTION_REPORT_DAYS', 730),
        'audit_days' => (int) env('TRACELESS_RETENTION_AUDIT_DAYS', 1095),
    ],

    'plans' => [
        'snapshot' => [
            'name' => 'Privacy Snapshot',
            'price' => 0,
            'cadence' => null,
            'features' => ['One identity scan', 'Exposure report', 'Removal instructions'],
        ],
        'deep_scan' => [
            'name' => 'Deep Scan',
            'price' => 24,
            'cadence' => 'monthly',
            'features' => ['Multiple profiles', 'Recurring monitoring', 'Reappearance alerts', 'Advanced reports'],
        ],
    ],

    'monitoring' => [
        'cadences' => [
            'daily' => 1,
            'weekly' => 7,
            'monthly' => 30,
        ],
    ],

    'broadcasting' => [
        // Livewire polling remains the guaranteed real-time transport. When
        // Reverb is configured, the same events are pushed over WebSockets.
        'enabled' => env('TRACELESS_BROADCAST', false),
    ],
];
