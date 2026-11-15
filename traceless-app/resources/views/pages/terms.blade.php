@extends('layouts.marketing')

@section('title', 'Terms of service · TraceLess')

@section('content')
    <div class="mx-auto max-w-3xl px-5 py-16 lg:px-10 lg:py-24">
        <p class="eyebrow">Trust // terms</p>
        <h1 class="display-title mt-3 text-[2.6rem] text-ink sm:text-[3.2rem]">Terms of service</h1>
        <p class="mt-3 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Last updated {{ date('F Y') }}</p>

        <div class="mt-10 space-y-8 font-body text-sm leading-relaxed text-charcoal/90">
            <section>
                <h2 class="font-editorial text-2xl text-ink">1. Authorised use only</h2>
                <p class="mt-2">You may only submit an identity that belongs to you or for which you hold explicit, documented permission. You must not use TraceLess to harass, stalk, dox, intimidate or surveil another person. We may suspend accounts that violate this rule.</p>
            </section>
            <section>
                <h2 class="font-editorial text-2xl text-ink">2. Public data, no passwords</h2>
                <p class="mt-2">TraceLess discovers public information. You must never submit passwords, private keys, one-time codes, government identifiers or another person's private records. We do not request third-party credentials and will not accept them.</p>
            </section>
            <section>
                <h2 class="font-editorial text-2xl text-ink">3. Estimates, not guarantees</h2>
                <p class="mt-2">Privacy scores, risk levels and exposure counts are estimates designed to prioritise action. Removal requests are tracked but cannot be guaranteed. A source may reject, ignore or reverse a request. TraceLess is not a law firm and does not provide legal advice.</p>
            </section>
            <section>
                <h2 class="font-editorial text-2xl text-ink">4. Accounts &amp; billing</h2>
                <p class="mt-2">You are responsible for safeguarding your account. Paid plans renew according to the plan terms shown at checkout. Demo deployments do not process real payments. You may cancel at any time; access continues until the end of the paid period.</p>
            </section>
            <section>
                <h2 class="font-editorial text-2xl text-ink">5. Acceptable use</h2>
                <p class="mt-2">Do not attempt to bypass rate limits, overwhelm sources, reverse engineer protected systems, scrape other users' data, or use the service to build a competing surveillance dataset. Automated removal workflows must respect each source's published policy.</p>
            </section>
            <section>
                <h2 class="font-editorial text-2xl text-ink">6. Availability &amp; liability</h2>
                <p class="mt-2">The service is provided on an “as is” basis to the extent permitted by law. Third-party sources change frequently and may block discovery. To the maximum extent permitted, TraceLess is not liable for indirect or consequential loss arising from use of the service.</p>
            </section>
            <section>
                <h2 class="font-editorial text-2xl text-ink">7. Termination &amp; data</h2>
                <p class="mt-2">You may delete your account at any time. We may suspend or terminate accounts that breach these terms. On termination, data is removed subject to legal retention and backup cycles.</p>
            </section>
            <section>
                <h2 class="font-editorial text-2xl text-ink">8. Open-source attribution</h2>
                <p class="mt-2">Username discovery builds on WhatsMyName (CC BY-SA 4.0) and user-scanner (MIT). Those projects remain the property of their authors; TraceLess does not claim ownership of upstream datasets.</p>
            </section>
        </div>

        <div class="mt-10 flex flex-wrap gap-3">
            <a href="{{ route('privacy') }}" class="btn-secondary">Privacy policy</a>
            <a href="{{ route('security') }}" class="btn-secondary">Security model</a>
        </div>
    </div>
@endsection
