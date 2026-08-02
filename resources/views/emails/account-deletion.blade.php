<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm account deletion</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: linear-gradient(135deg, #dc2626, #b91c1c); padding: 28px 32px; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; font-weight: 700; }
        .header p { color: rgba(255,255,255,.85); margin: 4px 0 0; font-size: 14px; }
        .body { padding: 32px; font-size: 15px; color: #111827; line-height: 1.6; }
        .button { display: inline-block; background: #dc2626; color: #ffffff !important; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 700; font-size: 15px; margin: 24px 0; }
        .warning { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 14px 16px; font-size: 14px; color: #991b1b; margin-top: 8px; }
        .ignore { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; font-size: 14px; color: #4b5563; margin-top: 20px; }
        .url { word-break: break-all; font-size: 12px; color: #6b7280; }
        .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 16px 32px; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Confirm account deletion</h1>
        <p>This link permanently deletes your GrinMuzik account</p>
    </div>

    <div class="body">
        <p>Hi {{ $recipientName }},</p>

        <p>
            We received a request to delete your GrinMuzik account. To go ahead,
            confirm below.
        </p>

        <div class="warning">
            <strong>This cannot be undone.</strong> Your profile, uploaded tracks
            and albums, playlists, likes and follows will be permanently removed.
        </div>

        <p style="text-align: center;">
            <a href="{{ $confirmUrl }}" class="button">Permanently delete my account</a>
        </p>

        <p class="url">
            If the button does not work, paste this into your browser:<br>
            {{ $confirmUrl }}
        </p>

        <p>
            The link expires in {{ $expiresInHours }} hours.
        </p>

        <div class="ignore">
            <strong>Didn't request this?</strong> Ignore this email and nothing
            will happen. Your account stays exactly as it is, and the link stops
            working once it expires. We never delete an account without a
            confirmation from this mailbox.
        </div>
    </div>

    <div class="footer">
        GrinMuzik &middot; This message was sent because a deletion request was made for this address.
    </div>
</div>
</body>
</html>
