<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6fb; color:#0f172a; font-family:Arial, Helvetica, sans-serif;">
<div style="display:none; max-height:0; overflow:hidden; opacity:0;">
    {{ $preheader }}
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6fb; margin:0; padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px; margin:0 auto;">
                <tr>
                    <td style="padding:0 18px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:24px; overflow:hidden; box-shadow:0 20px 60px rgba(15, 23, 42, 0.10);">
                            <tr>
                                <td style="padding:0; background:linear-gradient(135deg, {{ $primaryColor }} 0%, {{ $secondaryColor }} 100%);">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="padding:28px 32px 12px;">
                                                <div style="display:inline-block; padding:7px 12px; border-radius:999px; background:rgba(255,255,255,0.16); color:#ffffff; font-size:11px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase;">
                                                    {{ $eyebrow ?: $siteName }}
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:0 32px 34px;">
                                                <div style="font-size:32px; line-height:1.15; font-weight:800; color:#ffffff;">
                                                    {{ $title }}
                                                </div>
                                                @if($intro)
                                                    <div style="margin-top:14px; max-width:500px; font-size:16px; line-height:1.7; color:rgba(255,255,255,0.88);">
                                                        {{ $intro }}
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:32px;">
                                    @if($recipientName)
                                        <div style="font-size:15px; line-height:1.7; color:#334155; margin-bottom:14px;">
                                            Hi {{ $recipientName }},
                                        </div>
                                    @endif

                                    @foreach($messageLines as $line)
                                        <div style="font-size:15px; line-height:1.8; color:#334155; margin-bottom:14px; white-space:pre-line;">{{ $line }}</div>
                                    @endforeach

                                    @if(!empty($highlights))
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:26px 0 10px;">
                                            <tr>
                                                <td>
                                                    @foreach($highlights as $highlight)
                                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px; border:1px solid #e2e8f0; border-radius:18px; background:#f8fafc;">
                                                            <tr>
                                                                <td style="padding:16px 18px;">
                                                                    <div style="font-size:11px; font-weight:700; letter-spacing:0.10em; text-transform:uppercase; color:#64748b; margin-bottom:6px;">
                                                                        {{ $highlight['label'] }}
                                                                    </div>
                                                                    <div style="font-size:16px; font-weight:700; color:#0f172a; line-height:1.5;">
                                                                        {{ $highlight['value'] }}
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    @endforeach
                                                </td>
                                            </tr>
                                        </table>
                                    @endif

                                    @if($ctaLabel && $ctaUrl)
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:28px;">
                                            <tr>
                                                <td style="border-radius:999px; background:{{ $primaryColor }};">
                                                    <a href="{{ $ctaUrl }}"
                                                       style="display:inline-block; padding:14px 22px; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none;">
                                                        {{ $ctaLabel }}
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    @endif

                                    @if($secondaryCtaLabel && $secondaryCtaUrl)
                                        <div style="margin-top:16px;">
                                            <a href="{{ $secondaryCtaUrl }}"
                                               style="font-size:14px; font-weight:700; color:{{ $primaryColor }}; text-decoration:none;">
                                                {{ $secondaryCtaLabel }} &rarr;
                                            </a>
                                        </div>
                                    @endif
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:0 32px 32px;">
                                    <div style="border-top:1px solid #e2e8f0; padding-top:20px; font-size:12px; line-height:1.7; color:#64748b;">
                                        @if($footerNote)
                                            <div style="margin-bottom:8px;">{{ $footerNote }}</div>
                                        @endif
                                        <div>{{ $siteName }} · <a href="{{ $siteUrl }}" style="color:{{ $primaryColor }}; text-decoration:none;">{{ $siteUrl }}</a></div>
                                        <div style="margin-top:6px;">Need help? <a href="{{ $contactUrl }}" style="color:{{ $primaryColor }}; text-decoration:none;">Contact support</a>.</div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
