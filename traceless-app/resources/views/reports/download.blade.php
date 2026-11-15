@php
    $score = (int) ($summary['privacy_score'] ?? $report->privacy_score);
    $scoreColor = $score >= 85 ? '#16B981' : ($score >= 65 ? '#246BFE' : ($score >= 40 ? '#D99000' : '#E44B5F'));
    $findingsByRisk = $findings->groupBy('risk');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report->title }} — TraceLess</title>
    <style>
        :root { --ink:#101217; --muted:#626977; --line:#e5e7eb; --blue:#246BFE; }
        * { box-sizing: border-box; }
        body { margin:0; background:#f6f8fb; color:var(--ink); font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; font-size:13px; line-height:1.55; }
        .page { max-width:920px; margin:24px auto; background:#fff; padding:48px 52px; box-shadow:0 20px 60px -30px rgba(16,18,23,.25); }
        .mono { font-family:"JetBrains Mono",ui-monospace,SFMono-Regular,Menlo,monospace; }
        .eyebrow { font-size:9px; letter-spacing:.2em; text-transform:uppercase; color:var(--blue); font-weight:600; }
        h1 { font-family:"Bodoni Moda",Georgia,serif; font-size:38px; line-height:1.05; margin:8px 0 4px; font-weight:500; }
        h2 { font-family:"Bodoni Moda",Georgia,serif; font-size:21px; margin:0 0 10px; font-weight:500; }
        h3 { font-size:11px; text-transform:uppercase; letter-spacing:.14em; color:var(--muted); margin:0 0 8px; font-family:"JetBrains Mono",monospace; }
        .header { display:flex; justify-content:space-between; align-items:flex-start; gap:24px; border-bottom:1px solid var(--line); padding-bottom:20px; }
        .brand { display:flex; align-items:center; gap:10px; }
        .brand-mark { width:34px; height:34px; border-radius:10px; background:var(--ink); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-family:"JetBrains Mono",monospace; font-size:12px; }
        .grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin:22px 0; }
        .card { border:1px solid var(--line); border-radius:12px; padding:14px; }
        .card .value { font-family:"JetBrains Mono",monospace; font-size:22px; font-weight:600; margin-top:4px; }
        .card .label { font-size:9px; text-transform:uppercase; letter-spacing:.12em; color:var(--muted); }
        .score { display:flex; align-items:center; gap:22px; border:1px solid var(--line); border-radius:16px; padding:20px; margin:20px 0; }
        .score-ring { width:96px; height:96px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-family:"JetBrains Mono",monospace; font-size:28px; font-weight:600; border:8px solid; }
        table { width:100%; border-collapse:collapse; font-size:11px; }
        th { text-align:left; font-family:"JetBrains Mono",monospace; font-size:9px; text-transform:uppercase; letter-spacing:.1em; color:var(--muted); border-bottom:1px solid var(--line); padding:8px 6px; }
        td { border-bottom:1px solid #f0f2f5; padding:9px 6px; vertical-align:top; }
        .risk { font-family:"JetBrains Mono",monospace; font-size:9px; font-weight:600; padding:2px 7px; border-radius:999px; display:inline-block; text-transform:uppercase; }
        .risk-critical { background:#fdeaed; color:#c62f45; }
        .risk-high { background:#fdeaed; color:#E44B5F; }
        .risk-moderate { background:#fdf3df; color:#a86f00; }
        .risk-low { background:#e8f1ff; color:#246BFE; }
        .note { border:1px solid var(--line); background:#f8fafc; border-radius:12px; padding:14px 16px; font-size:11px; color:var(--muted); }
        .footer { margin-top:26px; border-top:1px solid var(--line); padding-top:14px; font-size:9px; color:var(--muted); font-family:"JetBrains Mono",monospace; display:flex; justify-content:space-between; gap:12px; }
        .sig { word-break:break-all; }
        .badge-demo { background:#fdf3df; color:#a86f00; border:1px solid #f2dfb6; padding:2px 8px; border-radius:999px; font-size:9px; font-family:"JetBrains Mono",monospace; }
        @media print {
            body { background:#fff; }
            .page { margin:0; box-shadow:none; max-width:none; padding:20mm 16mm; }
            .no-print { display:none; }
            tr { break-inside:avoid; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div>
                <p class="eyebrow">TraceLess // privacy dossier</p>
                <h1>{{ $report->title }}</h1>
                <p class="mono" style="font-size:10px;color:var(--muted);margin:0;">
                    Report #{{ $report->id }} · Generated {{ $report->generated_at?->format('d M Y, H:i') }} · {{ str($report->report_type)->replace('_', ' ')->title() }}
                </p>
            </div>
            <div class="brand">
                <div class="brand-mark">TL</div>
                @if($report->demo_mode)<span class="badge-demo">DEMO DATA</span>@endif
            </div>
        </div>

        <div class="score">
            <div class="score-ring" style="border-color:{{ $scoreColor }};color:{{ $scoreColor }};">{{ $score }}</div>
            <div>
                <h2 style="margin:0;">Estimated privacy posture</h2>
                <p style="margin:6px 0 0;color:var(--muted);font-size:12px;max-width:520px;">
                    Prepared for <strong style="color:var(--ink);">{{ $summary['profile'] ?? $profile->full_name }}</strong>.
                    Exposure level: <strong style="color:var(--ink);">{{ ucfirst($summary['exposure_level'] ?? 'unknown') }}</strong>.
                    This score helps prioritise actions; it is not a guarantee of privacy or removal.
                </p>
            </div>
        </div>

        <div class="grid">
            <div class="card"><p class="label">Total findings</p><p class="value">{{ $summary['findings']['total'] ?? $findings->count() }}</p></div>
            <div class="card"><p class="label">Active public</p><p class="value">{{ $summary['findings']['active'] ?? $findings->whereNotIn('status', ['removed'])->count() }}</p></div>
            <div class="card"><p class="label">Removed</p><p class="value">{{ $summary['findings']['removed'] ?? $findings->where('status', 'removed')->count() }}</p></div>
            <div class="card"><p class="label">Credential exposures</p><p class="value">{{ $summary['credential_exposures'] ?? $profile->credentialExposures()->count() }}</p></div>
        </div>

        <div style="margin:26px 0;">
            <h2>Exposure ledger</h2>
            <p style="margin:0 0 12px;color:var(--muted);font-size:11px;">Ordered by risk. Public-source records only.</p>
            <table>
                <thead>
                    <tr><th>Source</th><th>Identifier</th><th>Risk</th><th>Status</th><th>Removal</th></tr>
                </thead>
                <tbody>
                    @forelse($findings as $finding)
                        <tr>
                            <td>
                                <strong>{{ $finding->source }}</strong>
                                @if($finding->url)
                                    <div class="mono" style="font-size:9px;color:var(--muted);word-break:break-all;">{{ \Illuminate\Support\Str::limit($finding->url, 64) }}</div>
                                @endif
                            </td>
                            <td class="mono" style="font-size:10px;">{{ $finding->identifier_value }}</td>
                            <td><span class="risk risk-{{ $finding->risk }}">{{ $finding->risk }}</span></td>
                            <td>{{ str($finding->status)->replace('_', ' ')->title() }}</td>
                            <td>{{ $finding->removalTask ? str($finding->removalTask->status)->replace('_', ' ')->title() : 'Not started' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="color:var(--muted);padding:16px 6px;">No public findings were recorded for this identity.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($profile->credentialExposures()->exists())
            <div style="margin:26px 0;">
                <h2>Credential exposure metadata</h2>
                <p style="margin:0 0 10px;color:var(--muted);font-size:11px;">Public breach metadata only. TraceLess never stores passwords or plaintext secrets.</p>
                <table>
                    <thead><tr><th>Service</th><th>Email</th><th>Breach date</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($profile->credentialExposures()->latest()->get() as $credential)
                            <tr>
                                <td><strong>{{ $credential->service_name }}</strong></td>
                                <td class="mono" style="font-size:10px;">{{ $credential->email }}</td>
                                <td>{{ $credential->breach_date ?? 'Unknown' }}</td>
                                <td>{{ $credential->is_resolved ? 'Actioned' : 'Action required' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="note" style="margin-top:24px;">
            <strong style="color:var(--ink);">Important:</strong> {{ $summary['disclaimer'] ?? 'This score is an estimate. Removal is not guaranteed.' }}
            {{ $summary['attribution'] ?? '' }}
        </div>

        <div class="footer">
            <div>
                <div>TraceLess · public footprint intelligence</div>
                <div class="sig">sig {{ $report->signature ?? 'unsigned' }}</div>
            </div>
            <div style="text-align:right;">
                <div>Downloaded {{ $report->download_count }} time(s)</div>
                <div>Keep this document private — it contains public records linked to your identity.</div>
            </div>
        </div>
    </div>
</body>
</html>
