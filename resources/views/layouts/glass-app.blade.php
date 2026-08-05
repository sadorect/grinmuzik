<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">

    @include('partials.theme-head')
    @include('partials.seo-head', ['pageTitle' => $title ?? null])
    @php
        $appIconUrl = route('pwa.icon');
    @endphp
    <link rel="icon" href="{{ $appIconUrl }}">
    <link rel="shortcut icon" href="{{ $appIconUrl }}">
    <link rel="apple-touch-icon" href="{{ $appIconUrl }}">
    <style>
        :root {
@foreach($themeCssVariables as $name => $value)
            {{ $name }}: {{ $value }};
@endforeach
        }
    </style>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600,700&family=inter:300,400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    @stack('head')
    @stack('styles')
</head>
<body class="glass-gradient-light min-h-screen antialiased transition-colors duration-300"
      x-data="{ showBackToTop: false }"
      x-on:scroll.window.throttle.150ms="showBackToTop = window.scrollY > 500">
@php
    $currentRouteName = request()->route()?->getName();
    $publicShell = $publicShell ?? ($currentRouteName === 'search' ? 'search-sidebar' : 'default');
    $useSearchSidebar = $publicShell === 'search-sidebar';
    $primaryNavItems = [
        ['label' => 'Browse', 'route' => 'home'],
        ['label' => 'Search', 'route' => 'search'],
        ['label' => 'Charts', 'route' => 'charts'],
        ['label' => 'New Releases', 'route' => 'new-releases'],
        ['label' => 'Playlists', 'route' => 'playlists.public'],
    ];
    $inboxUnread = 0;
    if (auth()->check()) {
        try {
            $inboxUnread = \App\Models\InternalMessage::query()
                ->forUser(auth()->user())
                ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', auth()->id()))
                ->count();
        } catch (\Throwable) {
            $inboxUnread = 0;
        }
    }
@endphp
<div id="app" class="min-h-screen" data-public-shell="{{ $publicShell }}">

    @if($useSearchSidebar)
        <aside aria-label="Search page sidebar" class="fixed inset-y-0 left-0 z-40 hidden w-72 flex-col border-r border-slate-900/80 bg-slate-950/94 px-5 py-6 text-slate-100 shadow-[28px_0_60px_rgba(2,6,23,0.42)] backdrop-blur-2xl md:flex">
            <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-2xl border border-white/[0.06] bg-white/[0.03] px-3 py-2 transition hover:bg-white/[0.06]" wire:navigate>
                <x-application-logo class="h-8 w-auto object-contain" />
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-slate-200">Search</p>
                </div>
            </a>

            <div class="mt-8 rounded-[1.8rem] border border-white/10 bg-black/25 p-4 shadow-[0_18px_40px_rgba(2,6,23,0.25)]">
                <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-primary-100">Navigate</p>
                <div class="mt-3 space-y-1.5">
                    @foreach($primaryNavItems as $item)
                        @php($isActive = $currentRouteName === $item['route'])
                        <a
                            href="{{ route($item['route']) }}"
                            wire:navigate
                            class="flex items-center justify-between rounded-2xl border px-4 py-3 text-sm font-bold transition {{ $isActive ? 'border-primary/70 bg-primary text-white shadow-[0_14px_28px_rgba(79,70,229,0.28)]' : 'border-white/[0.08] bg-white/[0.06] text-slate-50 hover:border-white/[0.14] hover:bg-white/[0.11] hover:text-white' }}"
                        >
                            <span>{{ $item['label'] }}</span>
                            @if($isActive)
                                <span class="h-2.5 w-2.5 rounded-full bg-white/90"></span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="mt-4 space-y-2">
                @include('partials.theme-toggle', ['showLabel' => true, 'class' => 'w-full justify-between border-white/10 bg-black/25 text-sm font-semibold text-slate-50 shadow-[0_14px_30px_rgba(2,6,23,0.18)] hover:bg-white/[0.08]'])
                <div
                    x-data="{ canInstall: false, onIos: false }"
                    x-on:grin-install-state.window="canInstall = !!$event.detail.deferredPrompt; onIos = $event.detail.canManuallyInstallOnIos && !$event.detail.installed"
                    x-show="canInstall || onIos"
                    style="display:none"
                >
                    <button
                        type="button"
                        @click="$dispatch('grin-open-install-prompt')"
                        class="glass-btn glass-btn-hover flex w-full items-center justify-between gap-2 rounded-xl border-white/10 bg-black/25 px-3 py-2.5 text-sm font-semibold text-slate-50 shadow-[0_14px_30px_rgba(2,6,23,0.18)] hover:bg-white/[0.08]"
                    >
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Install App
                        </span>
                    </button>
                </div>
            </div>

            <div class="mt-auto rounded-[1.8rem] border border-white/10 bg-black/25 p-4 shadow-[0_18px_40px_rgba(2,6,23,0.25)]">
                @auth
                    <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-slate-200">Account</p>
                    <div class="mt-3 space-y-1.5">
                        <a href="{{ route('dashboard') }}" wire:navigate class="block rounded-2xl border border-white/[0.08] bg-white/[0.06] px-4 py-3 text-sm font-bold text-slate-50 transition hover:border-white/[0.14] hover:bg-white/[0.11] hover:text-white">Dashboard</a>
                        <a href="{{ route('inbox') }}" wire:navigate class="flex items-center justify-between rounded-2xl border border-white/[0.08] bg-white/[0.06] px-4 py-3 text-sm font-bold text-slate-50 transition hover:border-white/[0.14] hover:bg-white/[0.11] hover:text-white">
                            <span>Inbox</span>
                            @if($inboxUnread > 0)
                                <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary-500 px-1.5 text-[10px] font-bold text-white">{{ $inboxUnread }}</span>
                            @endif
                        </a>
                        <a href="{{ route('profile') }}" wire:navigate class="block rounded-2xl border border-white/[0.08] bg-white/[0.06] px-4 py-3 text-sm font-bold text-slate-50 transition hover:border-white/[0.14] hover:bg-white/[0.11] hover:text-white">Account Settings</a>
                        @if(Auth::user()->isArtist())
                            <a href="{{ route('artist.edit-profile') }}" wire:navigate class="block rounded-2xl border border-white/[0.08] bg-white/[0.06] px-4 py-3 text-sm font-bold text-slate-50 transition hover:border-white/[0.14] hover:bg-white/[0.11] hover:text-white">Artist Profile</a>
                            <a href="{{ route('artist.tracks') }}" wire:navigate class="block rounded-2xl border border-white/[0.08] bg-white/[0.06] px-4 py-3 text-sm font-bold text-slate-50 transition hover:border-white/[0.14] hover:bg-white/[0.11] hover:text-white">My Music</a>
                        @endif
                        @if(Auth::user()->isAdmin())
                            <a href="{{ url('/musicdirector') }}" class="block rounded-2xl border border-white/[0.08] bg-white/[0.06] px-4 py-3 text-sm font-bold text-slate-50 transition hover:border-white/[0.14] hover:bg-white/[0.11] hover:text-white">Admin Panel</a>
                        @endif
                        <div x-data="pushNotificationToggle()" x-init="init()" x-show="supported" style="display:none">
                            <button
                                type="button"
                                @click="toggle()"
                                :disabled="busy"
                                class="flex w-full items-center gap-2 rounded-2xl border border-white/[0.08] bg-white/[0.06] px-4 py-3 text-left text-sm font-bold text-slate-50 transition hover:border-white/[0.14] hover:bg-white/[0.11] hover:text-white disabled:opacity-50"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                <span x-text="subscribed ? 'Disable Notifications' : 'Enable Notifications'"></span>
                            </button>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full rounded-2xl border border-white/[0.08] bg-white/[0.06] px-4 py-3 text-left text-sm font-bold text-slate-50 transition hover:border-white/[0.14] hover:bg-white/[0.11] hover:text-white">Log Out</button>
                        </form>
                    </div>
                @else
                    <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-slate-200">Join In</p>
                    <div class="mt-3 space-y-2.5">
                        <a href="{{ route('register') }}" wire:navigate class="block rounded-2xl bg-primary px-4 py-3 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500">Sign Up Free</a>
                        <a href="{{ route('login') }}" wire:navigate class="block rounded-2xl border border-white/[0.08] bg-white/[0.06] px-4 py-3 text-center text-sm font-bold text-slate-50 transition hover:border-white/[0.14] hover:bg-white/[0.11] hover:text-white">Log In</a>
                    </div>
                @endauth
            </div>
        </aside>
    @endif

    {{-- â”€â”€ Navigation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
    <nav class="glass-nav sticky top-0 z-50 {{ $useSearchSidebar ? 'md:hidden' : '' }}">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                {{-- Logo --}}
                <a href="{{ route('home') }}" class="flex items-center text-xl font-bold text-gray-800 hover:text-primary-500 transition-colors" wire:navigate>
                    <x-application-logo class="h-7 w-auto object-contain" />
                </a>

                {{-- Desktop links --}}
                <div class="hidden md:flex items-center gap-7 text-sm">
                    <a href="{{ route('home') }}" class="text-gray-700 hover:text-primary-500 transition" wire:navigate>Browse</a>
                    <a href="{{ route('search') }}" class="text-gray-700 hover:text-primary-500 transition" wire:navigate>Search</a>
                    <a href="{{ route('charts') }}" class="text-gray-700 hover:text-primary-500 transition" wire:navigate>Charts</a>
                    <a href="{{ route('new-releases') }}" class="text-gray-700 hover:text-primary-500 transition" wire:navigate>New Releases</a>
                    <a href="{{ route('playlists.public') }}" class="text-gray-700 hover:text-primary-500 transition" wire:navigate>Playlists</a>

                    <div
                        x-data="{ canInstall: false, onIos: false }"
                        x-on:grin-install-state.window="canInstall = !!$event.detail.deferredPrompt; onIos = $event.detail.canManuallyInstallOnIos && !$event.detail.installed"
                        x-show="canInstall || onIos"
                        style="display:none"
                    >
                        <button
                            type="button"
                            @click="$dispatch('grin-open-install-prompt')"
                            class="glass-btn glass-btn-hover inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-gray-700"
                            aria-label="Install app"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Install App</span>
                        </button>
                    </div>

                    @auth
                        @if(Auth::user()->isArtist())
                            <a
                                href="{{ route('artist.upload-track') }}"
                                wire:navigate
                                class="glass-btn-primary glass-btn-primary-hover inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold shadow-sm"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V7m0 0-3.5 3.5M12 7l3.5 3.5M5 17.5A2.5 2.5 0 0 0 7.5 20h9a2.5 2.5 0 0 0 2.5-2.5" />
                                </svg>
                                <span>Upload</span>
                            </a>
                        @endif
                        <a href="{{ route('dashboard') }}" class="text-gray-700 hover:text-primary-500 transition" wire:navigate>Dashboard</a>
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="flex items-center gap-2 glass-btn glass-btn-hover py-1.5 px-3 text-sm">
                                <img src="{{ Auth::user()->getAvatarUrl() }}" class="w-6 h-6 rounded-full object-cover" alt="">
                                <span class="max-w-[100px] truncate">{{ Auth::user()->name }}</span>
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 011.06 0L10 11.94l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 9.28a.75.75 0 010-1.06z" clip-rule="evenodd"/></svg>
                            </button>
                            <div x-show="open" @click.outside="open = false" x-transition
                                 class="absolute right-0 mt-2 w-48 glass-panel rounded-xl shadow-glass overflow-hidden text-sm" style="display:none">
                                @if(Auth::user()->isArtist())
                                <a href="{{ route('artist.edit-profile') }}" wire:navigate class="flex items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-primary-50 hover:text-primary-600 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5h6m-6 4h6m-9 8h8a2 2 0 002-2V7a2 2 0 00-2-2h-2.586a1 1 0 01-.707-.293l-.828-.828A1 1 0 0011.172 3H8a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    Artist Profile
                                </a>
                                @endif
                                <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-primary-50 hover:text-primary-600 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    Account Settings
                                </a>
                                <a href="{{ route('inbox') }}" wire:navigate class="flex items-center justify-between gap-2 px-4 py-2.5 text-gray-700 hover:bg-primary-50 hover:text-primary-600 transition">
                                    <span class="flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        Inbox
                                    </span>
                                    @if($inboxUnread > 0)
                                        <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-primary-500 px-1 text-[9px] font-bold text-white">{{ $inboxUnread }}</span>
                                    @endif
                                </a>
                                @if(Auth::user()->isAdmin())
                                <a href="{{ url('/musicdirector') }}" class="flex items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-primary-50 hover:text-primary-600 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7.5l9-4 9 4m-18 0v9l9 4 9-4v-9m-18 0l9 4m0 9v-9m9-4l-9 4"/></svg>
                                    Admin Panel
                                </a>
                                @endif
                                @if(Auth::user()->isArtist())
                                <a href="{{ route('artist.tracks') }}" wire:navigate class="flex items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-primary-50 hover:text-primary-600 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13"/></svg>
                                    My Music
                                </a>
                                @endif
                                <div x-data="pushNotificationToggle()" x-init="init()" x-show="supported" style="display:none">
                                    <button
                                        type="button"
                                        @click="toggle()"
                                        :disabled="busy"
                                        class="flex w-full items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-primary-50 hover:text-primary-600 transition text-left disabled:opacity-50"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                        <span x-text="subscribed ? 'Disable Notifications' : 'Enable Notifications'"></span>
                                    </button>
                                </div>
                                <div class="my-1 border-t border-gray-100 dark:border-white/10"></div>
                                @include('partials.theme-toggle', [
                                    'showLabel' => true,
                                    'class' => 'flex w-full items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-primary-50 hover:text-primary-600 transition dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white',
                                ])
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full flex items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-primary-50 hover:text-primary-600 transition text-left">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                        Log Out
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        @include('partials.theme-toggle', [
                            'showLabel' => false,
                            'class' => 'glass-btn glass-btn-hover p-2 rounded-xl',
                        ])
                        <a href="{{ route('login') }}" class="text-gray-700 hover:text-primary-500 transition" wire:navigate>Login</a>
                        <a href="{{ route('register') }}" class="glass-btn-primary glass-btn-primary-hover px-4 py-2 rounded-xl text-sm font-medium" wire:navigate>Sign Up Free</a>
                    @endauth
                </div>

                {{-- Mobile hamburger --}}
                <div class="flex items-center gap-2 md:hidden">
                    @auth
                        @if(Auth::user()->isArtist())
                            <a
                                href="{{ route('artist.upload-track') }}"
                                wire:navigate
                                class="glass-btn-primary glass-btn-primary-hover inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm"
                                aria-label="Upload track"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V7m0 0-3.5 3.5M12 7l3.5 3.5M5 17.5A2.5 2.5 0 0 0 7.5 20h9a2.5 2.5 0 0 0 2.5-2.5" />
                                </svg>
                                <span>Upload</span>
                            </a>
                        @endif
                    @endauth
                    <button x-data x-on:click="$dispatch('toggle-mobile-nav')" class="glass-btn glass-btn-hover p-2 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    {{-- â”€â”€ Mobile Drawer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
    <div x-data="{ open: false }" x-on:toggle-mobile-nav.window="open = !open" class="md:hidden">
        <div x-show="open" x-transition @click="open = false" class="fixed inset-0 bg-black/30 z-[59] backdrop-blur-sm" style="display:none"></div>
        <div x-show="open" x-transition class="fixed inset-y-0 left-0 flex h-[100dvh] w-[86vw] max-w-xs flex-col overflow-hidden glass-panel z-[60] p-6 text-sm shadow-xl" style="display:none">
            <div class="mb-6 flex items-center justify-between gap-3">
                <a href="{{ route('home') }}" class="flex items-center text-gray-800" wire:navigate>
                    <x-application-logo class="h-7 w-auto object-contain" />
                </a>
                <button @click="open = false" class="rounded-full border border-white/50 bg-white/70 p-2 text-gray-500">
                    <span class="sr-only">Close menu</span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                <div class="space-y-1 rounded-2xl border border-white/50 bg-white/70 p-3">
                    <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">Discover</p>
                    @include('partials.theme-toggle', ['showLabel' => true, 'class' => 'w-full justify-between rounded-xl px-3 py-2.5 text-gray-700 transition hover:bg-primary-50 hover:text-primary-600'])
                    <a href="{{ route('home') }}" wire:navigate class="block rounded-xl px-3 py-2.5 text-gray-700 transition hover:bg-primary-50 hover:text-primary-600">Browse</a>
                    <a href="{{ route('search') }}" wire:navigate class="block rounded-xl px-3 py-2.5 text-gray-700 transition hover:bg-primary-50 hover:text-primary-600">Search</a>
                    <a href="{{ route('charts') }}" wire:navigate class="block rounded-xl px-3 py-2.5 text-gray-700 transition hover:bg-primary-50 hover:text-primary-600">Charts</a>
                    <a href="{{ route('new-releases') }}" wire:navigate class="block rounded-xl px-3 py-2.5 text-gray-700 transition hover:bg-primary-50 hover:text-primary-600">New Releases</a>
                    <a href="{{ route('playlists.public') }}" wire:navigate class="block rounded-xl px-3 py-2.5 text-gray-700 transition hover:bg-primary-50 hover:text-primary-600">Public Playlists</a>
                    <div
                        x-data="{ canInstall: false, onIos: false }"
                        x-on:grin-install-state.window="canInstall = !!$event.detail.deferredPrompt; onIos = $event.detail.canManuallyInstallOnIos && !$event.detail.installed"
                        x-show="canInstall || onIos"
                        style="display:none"
                    >
                        <button
                            type="button"
                            @click="$dispatch('grin-open-install-prompt')"
                            class="flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-gray-700 transition hover:bg-primary-50 hover:text-primary-600 text-left"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Install App
                        </button>
                    </div>
                </div>

                @auth
                    <div class="mt-4 space-y-1 rounded-2xl border border-white/50 bg-white/70 p-3">
                        <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">Account</p>
                        <a href="{{ route('dashboard') }}" wire:navigate class="block rounded-xl px-3 py-2.5 text-gray-700 transition hover:bg-primary-50 hover:text-primary-600">Dashboard</a>
                        <a href="{{ route('inbox') }}" wire:navigate class="flex items-center justify-between rounded-xl px-3 py-2.5 text-gray-700 transition hover:bg-primary-50 hover:text-primary-600">
                            <span>Inbox</span>
                            @if($inboxUnread > 0)
                                <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary-500 px-1.5 text-[10px] font-bold text-white">{{ $inboxUnread }}</span>
                            @endif
                        </a>
                        @if(Auth::user()->isArtist())
                            <a href="{{ route('artist.edit-profile') }}" wire:navigate class="block rounded-xl px-3 py-2.5 text-gray-700 transition hover:bg-primary-50 hover:text-primary-600">Artist Profile</a>
                        @endif
                        <a href="{{ route('profile') }}" wire:navigate class="block rounded-xl px-3 py-2.5 text-gray-700 transition hover:bg-primary-50 hover:text-primary-600">Account Settings</a>
                        <div x-data="pushNotificationToggle()" x-init="init()" x-show="supported" style="display:none">
                            <button
                                type="button"
                                @click="toggle()"
                                :disabled="busy"
                                class="flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-gray-700 transition hover:bg-primary-50 hover:text-primary-600 text-left disabled:opacity-50"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                <span x-text="subscribed ? 'Disable Notifications' : 'Enable Notifications'"></span>
                            </button>
                        </div>
                        @if(Auth::user()->isAdmin())
                            <a href="{{ url('/musicdirector') }}" class="block rounded-xl px-3 py-2.5 text-gray-700 transition hover:bg-primary-50 hover:text-primary-600">Admin Panel</a>
                        @endif
                        <a href="{{ route('playlists.mine') }}" wire:navigate class="block rounded-xl px-3 py-2.5 text-gray-700 transition hover:bg-primary-50 hover:text-primary-600">My Playlists</a>
                        @if(Auth::user()->isArtist())
                            <a href="{{ route('artist.tracks') }}" wire:navigate class="block rounded-xl px-3 py-2.5 text-gray-700 transition hover:bg-primary-50 hover:text-primary-600">My Music</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" class="pt-1">
                            @csrf
                            <button type="submit" class="w-full rounded-xl px-3 py-2.5 text-left text-gray-700 transition hover:bg-primary-50 hover:text-primary-600">Log Out</button>
                        </form>
                    </div>
                @else
                    <div class="mt-4 space-y-3">
                        <a href="{{ route('register') }}" wire:navigate class="block rounded-xl bg-primary px-4 py-3 text-center font-semibold text-white shadow-sm">Sign Up Free</a>
                        <a href="{{ route('login') }}" wire:navigate class="block rounded-xl border border-white/50 bg-white/70 px-4 py-3 text-center font-semibold text-gray-700">Log In</a>
                    </div>
                @endauth
            </div>
        </div>
    </div>

    {{-- â”€â”€ Page Content â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
    <div class="{{ $useSearchSidebar ? 'md:pl-72' : '' }}">
        <main class="pb-6">
            {{-- Session flash messages --}}
            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-transition:leave="transition ease-in duration-200" x-transition:leave-end="opacity-0"
                     class="mx-4 mt-4 flex items-start justify-between gap-3 rounded-2xl border border-green-200 bg-green-50 px-5 py-3 text-sm text-green-800 shadow-sm">
                    <span>{{ session('success') }}</span>
                    <button type="button" x-on:click="show = false" class="shrink-0 text-green-500 hover:text-green-700" aria-label="Dismiss">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @endif
            @if(session('error'))
                <div x-data="{ show: true }" x-show="show" x-transition:leave="transition ease-in duration-200" x-transition:leave-end="opacity-0"
                     class="mx-4 mt-4 flex items-start justify-between gap-3 rounded-2xl border border-red-200 bg-red-50 px-5 py-3 text-sm text-red-800 shadow-sm">
                    <span>{{ session('error') }}</span>
                    <button type="button" x-on:click="show = false" class="shrink-0 text-red-500 hover:text-red-700" aria-label="Dismiss">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @endif
            @if(session('warning'))
                <div x-data="{ show: true }" x-show="show" x-transition:leave="transition ease-in duration-200" x-transition:leave-end="opacity-0"
                     class="mx-4 mt-4 flex items-start justify-between gap-3 rounded-2xl border border-yellow-200 bg-yellow-50 px-5 py-3 text-sm text-yellow-800 shadow-sm">
                    <span>{{ session('warning') }}</span>
                    <button type="button" x-on:click="show = false" class="shrink-0 text-yellow-500 hover:text-yellow-700" aria-label="Dismiss">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @endif
            @if(session('info'))
                <div x-data="{ show: true }" x-show="show" x-transition:leave="transition ease-in duration-200" x-transition:leave-end="opacity-0"
                     class="mx-4 mt-4 flex items-start justify-between gap-3 rounded-2xl border border-blue-200 bg-blue-50 px-5 py-3 text-sm text-blue-800 shadow-sm">
                    <span>{{ session('info') }}</span>
                    <button type="button" x-on:click="show = false" class="shrink-0 text-blue-500 hover:text-blue-700" aria-label="Dismiss">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @endif

            {{ $slot ?? '' }}
            @yield('content')
        </main>

    {{-- â”€â”€ Site Footer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
    <footer class="bg-gray-900 text-gray-400 pb-32 pt-14">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Top grid --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-10 mb-12">

                {{-- Brand --}}
                <div class="col-span-2 sm:col-span-1">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 text-white font-black text-xl mb-3">
                        <x-application-logo class="h-7 w-auto object-contain" />
                    </a>
                    <p class="text-sm leading-relaxed mb-5">
                        Independent music. Real artists.<br>Support the creators you love.
                    </p>
                    @if(count($socialLinks))
                        <div class="flex gap-3">
                            @foreach($socialLinks as $social)
                                <a href="{{ $social['url'] }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="w-8 h-8 rounded-full bg-white/10 hover:bg-primary-500 flex items-center justify-center transition"
                                   aria-label="{{ $social['label'] }}">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="{{ $social['path'] }}"/></svg>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Explore --}}
                <div>
                    <h4 class="text-white font-semibold text-sm mb-4 uppercase tracking-wider">Explore</h4>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="{{ route('home') }}" class="hover:text-primary-400 transition">Home</a></li>
                        <li><a href="{{ route('browse') }}" class="hover:text-primary-400 transition">Browse Music</a></li>
                        <li><a href="{{ route('search') }}" class="hover:text-primary-400 transition">Search</a></li>
                        <li><a href="{{ route('charts') }}" wire:navigate class="hover:text-primary-400 transition">Charts</a></li>
                        <li><a href="{{ route('new-releases') }}" wire:navigate class="hover:text-primary-400 transition">New Releases</a></li>
                        <li><a href="{{ route('playlists.public') }}" wire:navigate class="hover:text-primary-400 transition">Public Playlists</a></li>
                        @auth
                            <li><a href="{{ route('listener.liked') }}" class="hover:text-primary-400 transition">Liked Tracks</a></li>
                            <li><a href="{{ route('playlists.mine') }}" class="hover:text-primary-400 transition">My Playlists</a></li>
                        @endauth
                    </ul>
                </div>

                {{-- For Artists --}}
                <div>
                    <h4 class="text-white font-semibold text-sm mb-4 uppercase tracking-wider">For Artists</h4>
                    <ul class="space-y-2.5 text-sm">
                        @auth
                            @if(Auth::user()->isArtist())
                                <li><a href="{{ route('artist.dashboard') }}" class="hover:text-primary-400 transition">Dashboard</a></li>
                                <li><a href="{{ route('artist.edit-profile') }}" class="hover:text-primary-400 transition">Edit Profile</a></li>
                                <li><a href="{{ route('artist.upload-track') }}" class="hover:text-primary-400 transition">Upload Track</a></li>
                                <li><a href="{{ route('artist.albums') }}" class="hover:text-primary-400 transition">My Albums</a></li>
                            @endif
                        @else
                            <li><a href="{{ route('register') }}" class="hover:text-primary-400 transition">Join as Artist</a></li>
                        @endauth
                        <li><a href="{{ route('artist-guide') }}" wire:navigate class="hover:text-primary-400 transition">Artist Guide</a></li>
                        <li><a href="{{ route('pricing') }}" wire:navigate class="hover:text-primary-400 transition">Creator Access</a></li>
                    </ul>
                </div>

                {{-- Company --}}
                <div>
                    <h4 class="text-white font-semibold text-sm mb-4 uppercase tracking-wider">Company</h4>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="{{ route('about') }}" wire:navigate class="hover:text-primary-400 transition">About Us</a></li>
                        <li><a href="{{ route('privacy') }}" wire:navigate class="hover:text-primary-400 transition">Privacy Policy</a></li>
                        <li><a href="{{ route('terms') }}" wire:navigate class="hover:text-primary-400 transition">Terms of Service</a></li>
                        <li><a href="{{ route('guidelines') }}" wire:navigate class="hover:text-primary-400 transition">Community Guidelines</a></li>
                        <li><a href="{{ route('contact') }}" wire:navigate class="hover:text-primary-400 transition">Contact</a></li>
                        {{-- Play requires the deletion route to be readily
                             discoverable, not just reachable by direct URL. --}}
                        <li><a href="{{ route('account.deletion') }}" wire:navigate class="hover:text-primary-400 transition">Delete Account</a></li>
                    </ul>

                    <div class="mt-5 hidden space-y-3 sm:block">
                        <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white/90">
                            <svg class="h-6 w-6 text-emerald-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M3.5 2.5 14.8 13 3.5 21.5c-.3-.6-.5-1.2-.5-1.9V4.4c0-.7.2-1.3.5-1.9Zm12.9 12 3.7 2.2c1.2.7 1.2 2.4 0 3.1l-4.1 2.3-4.8-4.5 5.2-3.1Zm3.7-7.2c1.2.7 1.2 2.4 0 3.1l-2.9 1.7-5.2-4.8 4.5-2.7 3.6 2.7ZM11.2 8.1l4.7 4.4-5.2 3.1L4.3 9.4l6.9-1.3Z"/>
                            </svg>
                            <div>
                                <p class="text-[11px] uppercase tracking-[0.18em] text-white/50">Coming Soon</p>
                                <p class="font-semibold">Google Play</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white/90">
                            <svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M16.7 12.7c0-2.1 1.7-3.1 1.8-3.2-1-1.5-2.6-1.7-3.1-1.7-1.3-.1-2.5.8-3.2.8s-1.7-.8-2.8-.8c-1.5 0-2.8.9-3.5 2.2-1.5 2.6-.4 6.5 1 8.5.7 1 1.5 2.1 2.6 2.1s1.5-.7 2.9-.7 1.8.7 2.9.7 1.9-1 2.5-2c.8-1.1 1.1-2.2 1.1-2.2-.1 0-2.2-.8-2.2-3.7Zm-2.1-6.3c.5-.7.9-1.6.8-2.6-.8 0-1.8.6-2.4 1.3-.5.6-.9 1.5-.8 2.5.9.1 1.8-.5 2.4-1.2Z"/>
                            </svg>
                            <div>
                                <p class="text-[11px] uppercase tracking-[0.18em] text-white/50">Coming Soon</p>
                                <p class="font-semibold">App Store</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-3 sm:hidden">
                    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white/90">
                        <svg class="h-6 w-6 text-emerald-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M3.5 2.5 14.8 13 3.5 21.5c-.3-.6-.5-1.2-.5-1.9V4.4c0-.7.2-1.3.5-1.9Zm12.9 12 3.7 2.2c1.2.7 1.2 2.4 0 3.1l-4.1 2.3-4.8-4.5 5.2-3.1Zm3.7-7.2c1.2.7 1.2 2.4 0 3.1l-2.9 1.7-5.2-4.8 4.5-2.7 3.6 2.7ZM11.2 8.1l4.7 4.4-5.2 3.1L4.3 9.4l6.9-1.3Z"/>
                        </svg>
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.18em] text-white/50">Coming Soon</p>
                            <p class="font-semibold">Google Play</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white/90">
                        <svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M16.7 12.7c0-2.1 1.7-3.1 1.8-3.2-1-1.5-2.6-1.7-3.1-1.7-1.3-.1-2.5.8-3.2.8s-1.7-.8-2.8-.8c-1.5 0-2.8.9-3.5 2.2-1.5 2.6-.4 6.5 1 8.5.7 1 1.5 2.1 2.6 2.1s1.5-.7 2.9-.7 1.8.7 2.9.7 1.9-1 2.5-2c.8-1.1 1.1-2.2 1.1-2.2-.1 0-2.2-.8-2.2-3.7Zm-2.1-6.3c.5-.7.9-1.6.8-2.6-.8 0-1.8.6-2.4 1.3-.5.6-.9 1.5-.8 2.5.9.1 1.8-.5 2.4-1.2Z"/>
                        </svg>
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.18em] text-white/50">Coming Soon</p>
                            <p class="font-semibold">App Store</p>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Divider --}}
            <div class="border-t border-white/10 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <p>&copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>

                {{-- Site visit counter --}}
                @php($footerViews = cache()->remember('footer_page_views', 300, static fn (): int => \App\Models\PageView::count()))
                @php($footerToday = cache()->remember('footer_page_views_today', 60, static fn (): int => \App\Models\PageView::today()->count()))
                <p class="flex items-center gap-1.5">
                    <svg class="w-3 h-3 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span class="text-gray-500">{{ number_format($footerViews) }} visits &middot; {{ number_format($footerToday) }} today</span>
                </p>

                <p class="flex items-center gap-1.5">
                    Made with <span class="text-primary-500">&hearts;</span> for independent artists
                </p>
            </div>

        </div>
    </footer>

        <button
            x-show="showBackToTop"
            x-transition.opacity.scale
            @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
            class="fixed bottom-36 right-4 z-40 inline-flex h-12 w-12 items-center justify-center rounded-full border border-white/20 bg-gray-900/90 text-white shadow-xl backdrop-blur-md transition hover:bg-primary md:bottom-40"
            style="display:none"
            aria-label="Back to top"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
            </svg>
        </button>
    </div>

    {{-- â”€â”€ Persistent Mini Player â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
    @livewire('mini-player')

    {{-- â”€â”€ Login-Required Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
    <div
        x-data="{
            show: false,
            title: 'Sign in to continue',
            message: 'You need an account to like tracks, download music, and more.',
            defaultLoginUrl: @js(route('login')),
            defaultRegisterUrl: @js(route('register')),
            loginUrl: @js(route('login')),
            registerUrl: @js(route('register')),
            openModal(detail) {
                // Livewire delivers positional dispatch params as an array
                // (['login-required']) and named params as an object — accept both.
                if (Array.isArray(detail)) {
                    detail = detail.length === 1 ? detail[0] : Object.assign({}, ...detail.filter(v => v && typeof v === 'object'));
                }

                if (detail !== 'login-required' && detail?.id !== 'login-required') {
                    return;
                }

                this.title = detail?.title || 'Sign in to continue';
                this.message = detail?.message || 'You need an account to like tracks, download music, and more.';
                this.loginUrl = detail?.loginUrl || this.defaultLoginUrl;
                this.registerUrl = detail?.registerUrl || this.defaultRegisterUrl;
                this.show = true;
            },
        }"
        @open-modal.window="openModal($event.detail)"
        x-show="show"
        x-transition.opacity
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
        style="display:none"
    >
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="show = false"></div>
        <div class="relative glass-panel max-w-sm w-full p-8 text-center rounded-2xl shadow-2xl z-10">
            <div class="w-14 h-14 rounded-2xl bg-primary-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900 mb-2" x-text="title"></h2>
            <p class="text-gray-500 text-sm mb-6" x-text="message"></p>
            <div class="flex flex-col gap-3">
                <a x-bind:href="loginUrl" class="glass-btn-primary glass-btn-primary-hover w-full py-2.5 rounded-xl text-sm font-semibold text-center">Sign In</a>
                <a x-bind:href="registerUrl" class="glass-btn glass-btn-hover w-full py-2.5 rounded-xl text-sm font-semibold text-center">Create Free Account</a>
            </div>
            <button @click="show = false" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

</div>

@include('partials.pwa-install')

@livewireScripts
@stack('scripts')
</body>
</html>
