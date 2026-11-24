<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deep scan started</title>
</head>
<body style="margin:0;padding:0;background:#F4F2EC;font-family:Inter,Helvetica,Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#F4F2EC;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;background:#FFFFFF;border:1px solid #E5E1D8;border-radius:20px;overflow:hidden;">
                    <tr>
                        <td style="background:#07111F;padding:24px 32px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;padding-right:14px;">
                                        <img src="{{ $logoUrl }}" width="44" height="44" alt="TraceLess" style="display:block;width:44px;height:44px;border:0;border-radius:10px;">
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <img src="{{ $wordmarkUrl }}" width="240" height="30" alt="TraceLess" style="display:block;width:240px;height:30px;border:0;">
                                        <span style="display:block;margin-top:6px;font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#7DE3FF;">Deep scan // in progress</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 12px;font-size:20px;line-height:1.3;color:#07111F;">Your deep scan has started</h1>
                            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#374151;">
                                TraceLess is now crawling public sources for <strong>{{ $identityName }}</strong> (reference {{ $scan->reference() }}).
                                This is the widest sweep we run, so it can take up to <strong>{{ $estimatedHours }} hours</strong>.
                            </p>
                            <p style="margin:0 0 20px;font-size:13px;line-height:1.6;color:#6B7280;">
                                You can close this page or sign out — progress is saved on the server and the same countdown
                                continues on any device when you sign back in.
                            </p>
                            <p style="margin:0 0 24px;">
                                <a href="{{ $statusUrl }}" style="display:inline-block;padding:12px 20px;border-radius:8px;background:#07111F;color:#FFFFFF;text-decoration:none;font-size:14px;font-weight:600;">View live progress</a>
                            </p>
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#9CA3AF;">
                                Only public sources are checked. No passwords are requested or tested. You can cancel the scan
                                from the progress page at any time.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;border-top:1px solid #E5E1D8;background:#FAF9F5;">
                            <p style="margin:0;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#9CA3AF;">Public data only · Consent-gated · No passwords stored</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
