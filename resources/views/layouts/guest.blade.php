<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.theme-head')
    @include('partials.seo-head', ['pageTitle' => $title ?? null])
    @php($appIconUrl = route('pwa.icon'))
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
<body class="glass-gradient-light min-h-screen antialiased transition-colors duration-300">

    {{-- Decorative background blobs --}}
    <div class="fixed inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-primary-200/30 blur-3xl dark:bg-primary-500/15"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 rounded-full bg-secondary-100/45 blur-3xl dark:bg-secondary-500/10"></div>
    </div>

    <div class="relative min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <div class="absolute right-4 top-4 sm:right-6 sm:top-6">
            @include('partials.theme-toggle', ['showLabel' => true])
        </div>

        {{-- Logo --}}
        <a href="{{ route('home') }}" wire:navigate class="mb-8 inline-flex items-center group">
            <x-application-logo class="h-8 w-auto object-contain transition-transform group-hover:scale-110" />
        </a>

        {{-- Card --}}
        <div class="glass-panel w-full max-w-md rounded-2xl shadow-glass px-8 py-8">
            {{ $slot }}
        </div>

        {{-- Footer link --}}
        <p class="mt-6 text-xs text-gray-400">
            &copy; {{ date('Y') }} {{ $siteName }} &middot; <a href="{{ route('home') }}" wire:navigate class="hover:text-primary-400 transition">Back to home</a>
        </p>
    </div>

    @include('partials.pwa-install')

    @livewireScripts
    @stack('scripts')
</body>
</html>
