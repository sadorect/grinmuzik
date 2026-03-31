<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; margin: 0; padding: 0; }
        .wrapper { max-width: 640px; margin: 40px auto; background: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 12px 30px rgba(15, 23, 42, .10); }
        .header { background: linear-gradient(135deg, #1d4ed8, #6366f1 55%, #ec4899); padding: 32px; color: #fff; }
        .eyebrow { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 12px; padding: 6px 12px; border-radius: 999px; background: rgba(255,255,255,.14); font-size: 11px; letter-spacing: .14em; text-transform: uppercase; font-weight: 700; }
        .title { margin: 0; font-size: 26px; font-weight: 800; }
        .subtitle { margin: 10px 0 0; font-size: 14px; line-height: 1.6; color: rgba(255,255,255,.86); }
        .body { padding: 32px; }
        .meta { display: grid; gap: 16px; margin-bottom: 24px; padding: 20px; border-radius: 16px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #64748b; margin-bottom: 4px; }
        .value { font-size: 14px; color: #0f172a; }
        .message { padding: 22px; border-radius: 18px; background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border: 1px solid #e2e8f0; color: #0f172a; font-size: 15px; line-height: 1.7; white-space: pre-wrap; }
        .footer { padding: 18px 32px 28px; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <div class="eyebrow">GrinMuzik Contact Desk</div>
        <h1 class="title">{{ $subjectLine }}</h1>
        <p class="subtitle">This reply was sent in response to your message to the GrinMuzik team.</p>
    </div>

    <div class="body">
        <div class="meta">
            <div>
                <div class="label">Original Subject</div>
                <div class="value">{{ $contactMessage->subject }}</div>
            </div>
            <div>
                <div class="label">Reply From</div>
                <div class="value">{{ $senderName }}</div>
            </div>
        </div>

        <div class="message">{{ $body }}</div>
    </div>

    <div class="footer">
        You are receiving this email because you contacted GrinMuzik through the site contact form.
    </div>
</div>
</body>
</html>
