<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $siteName ?? 'GrinMuzik' }} | Offline</title>
    <meta name="theme-color" content="{{ $themePrimaryColor ?? '#305CDE' }}">
    <style>
        :root {
            color-scheme: light;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Figtree, ui-sans-serif, system-ui, sans-serif;
            background:
                radial-gradient(circle at top right, rgba(48, 92, 222, 0.16), transparent 35%),
                radial-gradient(circle at bottom left, rgba(14, 165, 233, 0.12), transparent 28%),
                #f8fafc;
            color: #0f172a;
        }

        .card {
            width: min(100%, 420px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.92);
            box-shadow: 0 25px 60px rgba(15, 23, 42, 0.12);
            padding: 32px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 18px;
            background: rgba(48, 92, 222, 0.12);
            color: #305cde;
            margin-bottom: 18px;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 1.6rem;
            line-height: 1.2;
        }

        p {
            margin: 0 0 20px;
            color: #475569;
            line-height: 1.65;
        }

        a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 18px;
            border-radius: 14px;
            background: #305cde;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="badge" aria-hidden="true">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.5 16.5a5 5 0 017 0M5 13a10 10 0 0114 0M2 9.5A15 15 0 0122 9.5M12 20h.01" />
            </svg>
        </div>
        <h1>You're offline</h1>
        <p>{{ $siteName ?? 'GrinMuzik' }} needs a connection to load fresh music, artist pages, and your library. Reconnect and try again.</p>
        <a href="{{ route('home') }}">Try again</a>
    </main>
</body>
</html>
