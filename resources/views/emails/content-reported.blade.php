<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content reported</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { padding: 28px 32px; background: linear-gradient(135deg, #f59e0b, #d97706); }
        .header.urgent { background: linear-gradient(135deg, #dc2626, #991b1b); }
        .header h1 { color: #fff; margin: 0; font-size: 22px; font-weight: 700; }
        .header p { color: rgba(255,255,255,.85); margin: 4px 0 0; font-size: 14px; }
        .body { padding: 32px; }
        .field-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #9ca3af; margin-bottom: 4px; }
        .field-value { font-size: 15px; color: #111827; margin-bottom: 20px; }
        .details-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; font-size: 15px; color: #111827; line-height: 1.6; white-space: pre-wrap; }
        .urgent-note { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 14px 16px; font-size: 14px; color: #991b1b; margin-bottom: 20px; }
        .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 16px 32px; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header {{ $report->isUrgent() ? 'urgent' : '' }}">
        <h1>{{ $report->isUrgent() ? 'Urgent content report' : 'Content reported' }}</h1>
        <p>{{ $report->reasonLabel() }}</p>
    </div>

    <div class="body">
        @if ($report->isUrgent())
            <div class="urgent-note">
                <strong>This category is auto-hidden on a single report.</strong>
                The content is already out of public view and is waiting on your
                review.
            </div>
        @endif

        <div class="field-label">Content</div>
        <div class="field-value">
            {{ class_basename($report->reportable_type) }}
            @if ($report->reportable)
                &mdash; {{ $report->reportable->reportableLabel() }}
            @else
                &mdash; (no longer exists)
            @endif
        </div>

        <div class="field-label">Reported by</div>
        <div class="field-value">
            {{ $report->reporter?->name ?? $report->reporter_email ?? 'Anonymous' }}
        </div>

        <div class="field-label">Reason</div>
        <div class="field-value">{{ $report->reasonLabel() }}</div>

        @if ($report->details)
            <div class="field-label">Details</div>
            <div class="details-box">{{ $report->details }}</div>
        @endif
    </div>

    <div class="footer">
        Review it in the admin panel under Moderation &rarr; Content Reports.
    </div>
</div>
</body>
</html>
