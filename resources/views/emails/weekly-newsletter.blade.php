<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $draft->subject }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; margin: 0; padding: 0; }
        .wrapper { max-width: 640px; margin: 40px auto; background: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 12px 30px rgba(15, 23, 42, .10); }
        .header { background: linear-gradient(135deg, #1d4ed8, #6366f1 55%, #ec4899); padding: 32px; color: #fff; }
        .eyebrow { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 12px; padding: 6px 12px; border-radius: 999px; background: rgba(255,255,255,.14); font-size: 11px; letter-spacing: .14em; text-transform: uppercase; font-weight: 700; }
        .title { margin: 0; font-size: 26px; font-weight: 800; line-height: 1.2; }
        .subtitle { margin: 10px 0 0; font-size: 14px; line-height: 1.6; color: rgba(255,255,255,.86); }
        .body { padding: 32px; }
        .message-body { color: #1e293b; font-size: 15px; line-height: 1.7; }
        .message-body p { margin: 0 0 12px; }
        .message-body p:last-child { margin-bottom: 0; }
        .footer { padding: 18px 32px 28px; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; margin-top: 24px; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <div class="eyebrow">{{ $draft->period }}</div>
        <h1 class="title">{{ $draft->subject }}</h1>
        @if($copy->intro)<p class="subtitle">{{ $copy->intro }}</p>@endif
    </div>

    <div class="body">
        <div class="message-body">
            {!! $draft->body !!}
        </div>
    </div>

    <div class="footer">
        {{ $copy->footerNote }}
    </div>
</div>
</body>
</html>
