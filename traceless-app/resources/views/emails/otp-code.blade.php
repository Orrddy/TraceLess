<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your TraceLess code</title>
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
                                        <img src="{{ $logoUrl }}" width="44" height="44" alt="TraceLess" style="display:block;width:44px;height:44px;border:0;outline:none;text-decoration:none;border-radius:10px;">
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <img src="{{ $wordmarkUrl }}" width="240" height="30" alt="TraceLess" style="display:block;width:240px;height:30px;border:0;outline:none;text-decoration:none;font-family:Georgia,'Times New Roman',serif;font-size:24px;color:#FFFFFF;">
                                        <span style="display:block;margin-top:6px;font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#7DE3FF;">Secure access // one-time code</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 8px;font-size:15px;line-height:1.6;color:#374151;">Use this code to {{ $purposeLabel }}. It expires in {{ $expiresInMinutes }} minutes.</p>
                            <div style="margin:24px 0;padding:20px;text-align:center;border:1px solid #E5E1D8;border-radius:16px;background:#FAF9F5;">
                                <span style="font-family:'Courier New',monospace;font-size:36px;font-weight:700;letter-spacing:10px;color:#07111F;margin-left:10px;">{{ $code }}</span>
                            </div>
                            <p style="margin:0 0 16px;font-size:13px;line-height:1.6;color:#6B7280;">Enter this code in the TraceLess verification screen. The code can be used once and locks after {{ config('traceless.otp.max_attempts', 5) }} incorrect attempts.</p>
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#9CA3AF;">If you did not request this code, you can ignore this email. Nobody can sign in without the code. TraceLess will never ask for your email password or any social media password.</p>
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
