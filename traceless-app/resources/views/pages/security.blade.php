@extends('layouts.marketing')

@section('title', 'Security model — TraceLess')

@section('content')
    <div class="mx-auto max-w-4xl px-5 py-16 lg:px-10 lg:py-24">
        <p class="eyebrow">Trust // security</p>
        <h1 class="display-title mt-3 text-[2.6rem] text-ink sm:text-[3.2rem]">Security model</h1>
        <p class="mt-3 max-w-2xl font-body text-sm leading-relaxed text-muted">TraceLess is designed around a narrow promise: read public data, never request secrets, and keep every scan scoped to its owner.</p>

        <div class="mt-10 grid gap-4 sm:grid-cols-2">
            @foreach([
                ['lock', 'Zero password requests', 'We never ask for social media, email or banking passwords. Credential checks use public breach metadata only.'],
                ['policy', 'Ownership-scoped access', 'Policies and query scoping ensure a user can only see identities, scans, findings, reports and removals they own.'],
                ['password', 'Passwordless email OTP', 'Six-digit codes are stored only as a hash, expire after 10 minutes, are single-use and lock after five incorrect attempts.'],
                ['link_off', 'Signed, expiring downloads', 'Reports are served through temporary signed URLs with a per-report token. Every download is audited.'],
                ['shield', 'Consent gating', 'Scans refuse to queue without a current consent record. Withdrawing consent blocks new scans immediately.'],
                ['rule', 'Rate limiting and throttles', 'Code requests, code verification, account changes, scan creation, monitoring and report generation are rate limited to reduce abuse.'],
            ] as [$icon, $title, $body])
                <div class="glass-card p-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-hairline bg-elevated text-electric-blue">
                        <span class="material-symbols-outlined text-[19px]">{{ $icon }}</span>
                    </span>
                    <h2 class="mt-3 font-body text-sm font-semibold text-ink">{{ $title }}</h2>
                    <p class="mt-1 font-body text-xs leading-relaxed text-muted">{{ $body }}</p>
                </div>
            @endforeach
        </div>

        <section class="mt-12">
            <h2 class="font-editorial text-2xl text-ink">Local-first analysis boundary</h2>
            <p class="mt-2 font-body text-sm leading-relaxed text-charcoal/90">The discovery and risk-scoring pipeline runs against public sources through an isolated Python worker. Only normalised findings return to the application. Raw third-party responses are not retained. In demo mode, deterministic sample data is clearly labelled and never presented as real discovery.</p>
        </section>

        <section class="mt-8">
            <h2 class="font-editorial text-2xl text-ink">Data lifecycle</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                @foreach([
                    ['Ingest', 'Consent recorded, identifiers validated and encrypted.'],
                    ['Process', 'Queued scans normalise public findings and calculate an estimated score.'],
                    ['Expire', 'Retention commands remove scan artifacts and reports on schedule.'],
                ] as $i => [$title, $body])
                    <div class="glass-inset p-4">
                        <p class="font-mono text-[10px] uppercase tracking-wider text-electric-blue">0{{ $i + 1 }} · {{ $title }}</p>
                        <p class="mt-1 font-body text-xs leading-relaxed text-muted">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="mt-10 rounded-2xl border border-hairline bg-elevated p-6">
            <p class="mono-label">Responsible disclosure</p>
            <p class="mt-2 font-body text-sm leading-relaxed text-muted">If you find a vulnerability, report it privately to the operator of this deployment with reproduction steps. Do not access other users' data, run destructive tests, or publish details before a fix is available.</p>
        </section>
    </div>
@endsection
