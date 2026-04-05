<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $broadcast->interpolate($broadcast->subject, $recipient) }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; margin: 0; padding: 0; }
        .wrapper { max-width: 640px; margin: 40px auto; background: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 12px 30px rgba(15, 23, 42, .10); }
        .header { background: linear-gradient(135deg, #1d4ed8, #6366f1 55%, #ec4899); padding: 32px; color: #fff; }
        .eyebrow { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 12px; padding: 6px 12px; border-radius: 999px; background: rgba(255,255,255,.14); font-size: 11px; letter-spacing: .14em; text-transform: uppercase; font-weight: 700; }
        .title { margin: 0; font-size: 26px; font-weight: 800; line-height: 1.2; }
        .subtitle { margin: 10px 0 0; font-size: 14px; line-height: 1.6; color: rgba(255,255,255,.86); }
        .body { padding: 32px; }
        .greeting { font-size: 16px; color: #0f172a; margin: 0 0 20px; font-weight: 600; }
        .message-body { padding: 22px; border-radius: 18px; background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border: 1px solid #e2e8f0; color: #1e293b; font-size: 15px; line-height: 1.7; }
        .message-body p { margin: 0 0 12px; }
        .message-body p:last-child { margin-bottom: 0; }
        .cta-wrap { margin: 28px 0 0; text-align: center; }
        .cta-btn { display: inline-block; padding: 12px 28px; background: linear-gradient(135deg, #6366f1, #ec4899); color: #fff; font-weight: 700; font-size: 14px; text-decoration: none; border-radius: 12px; }
        .footer { padding: 18px 32px 28px; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; margin-top: 24px; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <div class="eyebrow">Message from {{ config('app.name') }}</div>
        <h1 class="title">{{ $broadcast->interpolate($broadcast->subject, $recipient) }}</h1>
        <p class="subtitle">A message has been sent to you from the {{ config('app.name') }} team.</p>
    </div>

    <div class="body">
        <p class="greeting">Hi {{ $recipient->name }},</p>

        <div class="message-body">
            {!! $broadcast->interpolate($broadcast->body, $recipient) !!}
        </div>

        <div class="cta-wrap">
            <a href="{{ url('/') }}" class="cta-btn">Visit {{ config('app.name') }}</a>
        </div>
    </div>

    <div class="footer">
        You are receiving this message because you are a member of {{ config('app.name') }}.<br>
        To view all your messages, <a href="{{ route('inbox') }}" style="color:#6366f1;">visit your inbox</a>.
    </div>
</div>
</body>
</html>
