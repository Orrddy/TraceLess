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
    | Free beta (v1.0)
    |--------------------------------------------------------------------------
    | While this is on every active account gets deep scans, monitoring and
    | deep reports regardless of subscription plan, and no billing path runs.
    | v2.0 turns this off and the plan gates take over again. The UI reads the
    | flag from config only (never from a client request).
    */
    'beta' => env('TRACELESS_BETA', true),

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
        // Deep scans probe hundreds of sources and run in the queue.
        'deep_timeout' => (int) env('TRACELESS_PYTHON_DEEP_TIMEOUT', 420),
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
        // Total wall-clock budget for a deep scan (discovery plus source
        // processing). A full deep crawl probes thousands of public sources, so
        // it is allowed to run for up to two hours. It stays below the queue
        // worker/job timeouts so the scan fails cleanly instead of being killed.
        'deep_wall_clock_seconds' => max(7200, (int) env('TRACELESS_DEEP_WALL_CLOCK', 7200)),
        // What the user is told to expect; the scan often finishes earlier.
        'deep_estimated_seconds' => (int) env('TRACELESS_DEEP_ESTIMATED', 7200),
        'snapshot_estimated_seconds' => (int) env('TRACELESS_SNAPSHOT_ESTIMATED', 120),
        // Live scans check every monitored username. Each one runs the worker
        // as a separate process, so the count bounds the wall-clock time.
        'max_usernames' => (int) env('TRACELESS_MAX_USERNAMES', 5),
        // Deep scans also check the primary email; one keeps runtime bounded.
        'max_emails' => (int) env('TRACELESS_MAX_EMAILS', 1),
        // One number keeps the probe sweep bounded; more would multiply runtime.
        'max_phones' => (int) env('TRACELESS_MAX_PHONES', 1),
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

    /*
    | IP exposure lookup. Keyless ipinfo works for low volume; a token raises
    | the limit. This returns network and location signals, never a fraud score.
    */
    'ip_lookup' => [
        'endpoint' => env('TRACELESS_IP_ENDPOINT', 'https://ipinfo.io'),
        'token' => env('TRACELESS_IP_TOKEN'),
        // AbuseIPDB adds the community abuse signal. Empty disables that half of
        // the check and the UI says so instead of implying it ran.
        'abuseipdb_key' => env('TRACELESS_ABUSEIPDB_KEY'),
        'abuseipdb_endpoint' => env('TRACELESS_ABUSEIPDB_ENDPOINT', 'https://api.abuseipdb.com/api/v2'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Landing-page IP intelligence
    |--------------------------------------------------------------------------
    | Multi-provider visitor intelligence with a 48h persistent cache. Every
    | provider key is optional: an empty key marks that provider "unavailable"
    | instead of calling it without credentials. Keys come from the environment
    | only and are never rendered, logged or returned to a client.
    */
    'ip_intelligence' => [
        'cache_ttl_seconds' => (int) env('IP_INTELLIGENCE_CACHE_TTL', 172800),
        // A degraded analysis (provider outage) must not poison the cache for
        // the full window: two to three providers get six hours, zero to one
        // gets ten minutes so a transient failure recovers quickly.
        'degraded_cache_ttl_seconds' => (int) env('IP_INTELLIGENCE_DEGRADED_CACHE_TTL', 21600),
        'minimal_cache_ttl_seconds' => (int) env('IP_INTELLIGENCE_MINIMAL_CACHE_TTL', 600),
        // Long enough for four 3s provider calls plus one retry round.
        'lock_seconds' => (int) env('IP_INTELLIGENCE_LOCK_SECONDS', 120),
        'timeout_seconds' => (float) env('IP_INTELLIGENCE_TIMEOUT', 3),
        'connect_timeout_seconds' => (float) env('IP_INTELLIGENCE_CONNECT_TIMEOUT', 2),
        'max_attempts' => (int) env('IP_INTELLIGENCE_MAX_ATTEMPTS', 2),
        'retry_backoff_ms' => (int) env('IP_INTELLIGENCE_RETRY_BACKOFF_MS', 250),
        // Expired cache rows are deleted once they are this many hours past
        // expiry, so an in-flight re-analysis is never pulled out from under it.
        'prune_after_hours' => (int) env('TRACELESS_IP_INTELLIGENCE_PRUNE_HOURS', 24),
        'providers' => [
            'ipapi_is' => [
                'label' => 'ipapi.is',
                'endpoint' => env('IPAPI_IS_ENDPOINT', 'https://api.ipapi.is/'),
                'key' => env('IPAPI_IS_KEY'),
                'weight' => 0.40,
            ],
            'proxycheck' => [
                'label' => 'proxycheck.io',
                'endpoint' => env('PROXYCHECK_ENDPOINT', 'https://proxycheck.io/v2/'),
                'key' => env('PROXYCHECK_KEY'),
                'weight' => 0.20,
            ],
            'abuseipdb' => [
                'label' => 'AbuseIPDB',
                'endpoint' => env('ABUSEIPDB_ENDPOINT', 'https://api.abuseipdb.com/api/v2/check'),
                'key' => env('ABUSEIPDB_KEY'),
                'weight' => 0.20,
            ],
            'ip2location' => [
                'label' => 'IP2Location.io',
                'endpoint' => env('IP2LOCATION_ENDPOINT', 'https://api.ip2location.io/'),
                'key' => env('IP2LOCATION_KEY'),
                'weight' => 0.20,
            ],
        ],
    ],

    'retention' => [
        // Days after which raw scan telemetry and downloaded report files expire.
        'scan_telemetry_days' => (int) env('TRACELESS_RETENTION_SCAN_DAYS', 365),
        'report_days' => (int) env('TRACELESS_RETENTION_REPORT_DAYS', 730),
        'audit_days' => (int) env('TRACELESS_RETENTION_AUDIT_DAYS', 1095),
        // Outbound delivery rows contain recipient addresses, so they must not
        // be kept forever. 90 days keeps ops forensics without stale PII.
        'outbound_email_days' => (int) env('TRACELESS_RETENTION_OUTBOUND_EMAIL_DAYS', 90),
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
        // When true, the dispatcher only runs for users with an active Deep Scan
        // plan. Keep false until subscriptions can actually be purchased.
        'enforce_plan' => (bool) env('TRACELESS_MONITORING_ENFORCE_PLAN', false),
    ],

    'broadcasting' => [
        // Livewire polling remains the guaranteed real-time transport. When
        // Reverb is configured, the same events are pushed over WebSockets.
        'enabled' => env('TRACELESS_BROADCAST', false),
    ],
];
