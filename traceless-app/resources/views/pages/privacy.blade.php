@extends('layouts.marketing')

@section('title', 'Privacy policy — TraceLess')

@section('content')
    <div class="mx-auto max-w-3xl px-5 py-16 lg:px-10 lg:py-24">
        <p class="eyebrow">Trust // privacy</p>
        <h1 class="display-title mt-3 text-[2.6rem] text-ink sm:text-[3.2rem]">Privacy policy</h1>
        <p class="mt-3 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Last updated {{ date('F Y') }} · Plain-language summary first</p>

        <div class="glass-card mt-8 p-6">
            <p class="mono-label">The short version</p>
            <ul class="mt-3 space-y-2 font-body text-sm leading-relaxed text-charcoal">
                <li class="flex gap-2"><span class="material-symbols-outlined text-[16px] text-emerald">check</span>We scan public sources only. No social passwords, no private inboxes, no purchased breach dumps.</li>
                <li class="flex gap-2"><span class="material-symbols-outlined text-[16px] text-emerald">check</span>You must consent and confirm authorisation before an identity can be scanned.</li>
                <li class="flex gap-2"><span class="material-symbols-outlined text-[16px] text-emerald">check</span>You can export or delete your data at any time from Settings.</li>
                <li class="flex gap-2"><span class="material-symbols-outlined text-[16px] text-emerald">check</span>Removal is never guaranteed. We track requests and provide manual playbooks.</li>
            </ul>
        </div>

        <div class="mt-10 space-y-8 font-body text-sm leading-relaxed text-charcoal/90">
            <section>
                <h2 class="font-editorial text-2xl text-ink">1. What we collect</h2>
                <p class="mt-2">Account data (name, email, hashed one-time sign-in codes and their delivery metadata, audit events), the public identifiers you choose to submit (name, aliases, public email, region), and the results of public-source checks. Accounts are passwordless: we never store an account password. We also never collect third-party service passwords, private messages, or non-public government identifiers.</p>
            </section>
            <section>
                <h2 class="font-editorial text-2xl text-ink">2. Why we process it</h2>
                <p class="mt-2">To discover publicly available records about the identity you are authorised to monitor, calculate an estimated privacy score, produce reports, and track opt-out requests. Consent is recorded with a timestamp and version. You can withdraw consent by deleting the identity or your account.</p>
            </section>
            <section>
                <h2 class="font-editorial text-2xl text-ink">3. Public-source boundary</h2>
                <p class="mt-2">Discovery is limited to public profiles, public directories, public breach metadata and pages that do not require bypassing authentication or paywalls. TraceLess does not scrape private accounts, defeat access controls, or purchase leaked credential databases.</p>
            </section>
            <section>
                <h2 class="font-editorial text-2xl text-ink">4. Processors &amp; transfers</h2>
                <p class="mt-2">Queue, database and mail providers may process data on our behalf under contract. Where regional transfer rules apply, we rely on appropriate safeguards. Demo deployments may run entirely locally with SQLite, database queues and log mail.</p>
            </section>
            <section>
                <h2 class="font-editorial text-2xl text-ink">5. Retention</h2>
                <p class="mt-2">Scan artifacts default to {{ config('traceless.retention.scan_telemetry_days', 365) }} days and reports to {{ config('traceless.retention.report_days', 730) }} days, after which scheduled retention removes them. Audit events are kept for security and abuse prevention. You can purge scan data instantly from Settings.</p>
            </section>
            <section>
                <h2 class="font-editorial text-2xl text-ink">6. Your rights</h2>
                <p class="mt-2">Depending on your jurisdiction you may request access, correction, export, deletion, restriction or objection. Use the in-app export and deletion tools, or contact the operator of this deployment. You may also lodge a complaint with your local data protection authority.</p>
            </section>
            <section>
                <h2 class="font-editorial text-2xl text-ink">7. Security</h2>
                <p class="mt-2">Sign-in uses single-use email codes that are stored only as a hash, expire quickly and lock after repeated failures. Access is scoped by ownership policies, and report downloads use signed, expiring links. See the <a href="{{ route('security') }}" class="text-electric-blue hover:underline">security model</a> for details.</p>
            </section>
            <section>
                <h2 class="font-editorial text-2xl text-ink">8. Contact</h2>
                <p class="mt-2">Privacy questions should go to the operator of this TraceLess deployment. Do not send passwords or identity documents by email.</p>
            </section>
        </div>

        <div class="mt-10 flex flex-wrap gap-3">
            <a href="{{ route('security') }}" class="btn-secondary">Security model</a>
            <a href="{{ route('terms') }}" class="btn-secondary">Terms of service</a>
        </div>
    </div>
@endsection
