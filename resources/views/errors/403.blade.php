<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access restricted - {{ $siteTitle }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full antialiased" style="background: linear-gradient(135deg, #eff6ff 0%, #e0f2fe 28%, #ede9fe 62%, #fdf2f8 100%);">

    <div class="fixed inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute -top-36 -right-24 h-[480px] w-[480px] rounded-full opacity-20"
             style="background: radial-gradient(circle, #60a5fa 0%, transparent 70%);"></div>
        <div class="absolute -bottom-28 -left-20 h-[360px] w-[360px] rounded-full opacity-20"
             style="background: radial-gradient(circle, #a78bfa 0%, transparent 70%);"></div>
        <div class="absolute left-1/2 top-1/2 h-[560px] w-[560px] -translate-x-1/2 -translate-y-1/2 rounded-full opacity-10"
             style="background: radial-gradient(circle, #f472b6 0%, transparent 60%);"></div>
    </div>

    <div class="relative flex min-h-screen flex-col items-center justify-center px-4 text-center">
        <a href="{{ route('home') }}" class="group mb-10 flex items-center gap-2">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 shadow-lg transition group-hover:scale-105">
                <svg class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 3v10.55A4 4 0 1014 17V7h4V3h-6z"/>
                </svg>
            </div>
            <span class="text-xl font-bold tracking-tight text-gray-800">{{ $siteName }}</span>
        </a>

        <div class="glass-panel w-full max-w-md rounded-3xl p-10 shadow-xl">
            <div class="mb-6 flex justify-center">
                <div class="relative">
                    <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-100 to-indigo-100">
                        <svg class="h-10 w-10 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2h-1V9a5 5 0 00-10 0v2H6a2 2 0 00-2 2v6a2 2 0 002 2zm3-10V9a3 3 0 016 0v2H9z"/>
                        </svg>
                    </div>
                    <span class="absolute -right-2 -top-2 flex h-7 w-7 items-center justify-center rounded-full bg-sky-500 text-xs font-bold text-white shadow">
                        403
                    </span>
                </div>
            </div>

            <h1 class="mb-2 text-3xl font-bold text-gray-800">Access restricted</h1>
            <p class="mb-8 text-sm leading-relaxed text-gray-500">
                You signed in successfully, but this area is reserved for accounts with higher access. If you think this is a mistake, contact the site administrator.
            </p>

            <div class="space-y-3">
                @auth
                    <a href="{{ route('dashboard') }}"
                       class="glass-btn-primary glass-btn-primary-hover flex w-full items-center justify-center gap-2 rounded-2xl px-6 py-3 text-sm font-semibold transition">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                        </svg>
                        Open dashboard
                    </a>
                @endauth

                <a href="{{ route('home') }}"
                   class="flex w-full items-center justify-center gap-2 rounded-2xl border border-white/50 bg-white/60 px-6 py-3 text-sm font-semibold text-gray-600 transition hover:bg-white/80">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 12h18M3 12l6-6m-6 6l6 6"/>
                    </svg>
                    Back to home
                </a>
            </div>
        </div>

        <p class="mt-8 text-xs text-gray-400">
            Error 403 &mdash; Access restricted
        </p>
    </div>

</body>
</html>
