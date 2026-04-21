<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.glass-app')] class extends Component
{
    public function with(): array
    {
        return [
            'showMonetizationPreview' => false,
            'seo' => [
                'title' => 'Creator Access',
                'description' => 'See how GrinMuzik helps artists set up profiles, upload music, and build their presence.',
                'canonical' => route('pricing'),
                'json_ld' => [
                    [
                        '@context' => 'https://schema.org',
                        '@type' => 'WebPage',
                        'name' => 'Creator Access',
                        'url' => route('pricing'),
                        'description' => 'See how GrinMuzik helps artists set up profiles, upload music, and build their presence.',
                    ],
                ],
            ],
            'creatorSteps' => [
                [
                    'step' => '01',
                    'title' => 'Upgrade your account',
                    'body' => 'Switch from listener mode into artist access whenever you are ready to publish music.',
                ],
                [
                    'step' => '02',
                    'title' => 'Build your profile',
                    'body' => 'Add your stage name, bio, genres, and links so listeners know exactly who they found.',
                ],
                [
                    'step' => '03',
                    'title' => 'Upload and organize',
                    'body' => 'Add tracks, cover art, moods, and albums from a workspace built around independent releases.',
                ],
            ],
            'creatorHighlights' => [
                'Artist profile setup with genre and social links.',
                'Track uploads with cover art, moods, and album organization.',
                'Public artist pages that connect discovery to your catalog.',
                'Listener-friendly dashboards, playlists, and charts around your releases.',
            ],
        ];
    }
};
?>

<div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="glass-card relative overflow-hidden rounded-[2rem] px-6 py-8 sm:px-8 sm:py-10">
            <div class="absolute inset-y-0 right-0 hidden w-2/5 bg-gradient-to-l from-primary/10 via-secondary/10 to-transparent lg:block"></div>
            <div class="absolute -left-10 top-16 h-40 w-40 rounded-full bg-emerald-200/35 blur-3xl"></div>
            <div class="absolute right-12 top-10 h-36 w-36 rounded-full bg-primary/20 blur-3xl"></div>

            <div class="relative z-10 grid gap-8 lg:grid-cols-[minmax(0,1.15fr),minmax(320px,0.85fr)] lg:items-center">
                <div class="max-w-3xl">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.28em] text-primary/70">Creator Access</p>
                    <h1 class="text-3xl font-black tracking-tight text-gray-900 sm:text-5xl">Build your artist presence before your first release drops.</h1>
                    <p class="mt-4 max-w-2xl text-sm leading-relaxed text-gray-600 sm:text-base">
                        This page is now focused on the creator journey: upgrading into artist access, setting up a profile, and getting music ready for discovery across GrinMuzik.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('register', ['role' => 'artist', 'show_role_guide' => 1]) }}"
                           wire:navigate
                           class="inline-flex items-center gap-2 rounded-full bg-primary px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500">
                            Start as an Artist
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                        <a href="{{ route('artist-guide') }}"
                           wire:navigate
                           class="inline-flex items-center gap-2 rounded-full border border-white/70 bg-white/85 px-5 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-white">
                            Read the Artist Guide
                        </a>
                    </div>
                </div>

                <div class="relative">
                    <div class="relative mx-auto max-w-sm rounded-[2rem] border border-white/70 bg-white/85 p-5 shadow-xl shadow-slate-900/5">
                        <div class="mb-5 flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-gray-400">Creator Snapshot</p>
                                <p class="mt-1 text-xl font-black text-gray-900">Artist Launch Flow</p>
                            </div>
                            <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-primary text-white shadow-md">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                                </svg>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-slate-600">Artist Profile</span>
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-700">Step 1</span>
                                </div>
                                <div class="mt-3 h-2 rounded-full bg-white">
                                    <div class="h-2 w-10/12 rounded-full bg-gradient-to-r from-primary to-secondary"></div>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-white bg-gradient-to-br from-primary-50 via-white to-primary-50 p-4">
                                    <p class="text-lg font-black text-gray-900">Tracks</p>
                                    <p class="mt-1 text-sm text-gray-500">Upload audio, artwork, moods, and release details from one place.</p>
                                </div>
                                <div class="rounded-2xl border border-white bg-gradient-to-br from-emerald-50 via-white to-secondary-50 p-4">
                                    <p class="text-lg font-black text-gray-900">Discovery</p>
                                    <p class="mt-1 text-sm text-gray-500">Show up with a polished profile across browse, search, and public artist pages.</p>
                                </div>
                            </div>

                            <div class="rounded-[1.6rem] bg-slate-900 p-5 text-white">
                                <div class="flex items-end gap-2">
                                    <div class="h-10 w-3 rounded-full bg-white/30"></div>
                                    <div class="h-16 w-3 rounded-full bg-white/45"></div>
                                    <div class="h-8 w-3 rounded-full bg-white/25"></div>
                                    <div class="h-20 w-3 rounded-full bg-primary-300"></div>
                                    <div class="h-12 w-3 rounded-full bg-white/35"></div>
                                    <div class="h-16 w-3 rounded-full bg-secondary-300"></div>
                                    <div class="h-9 w-3 rounded-full bg-white/25"></div>
                                    <div class="h-14 w-3 rounded-full bg-white/40"></div>
                                </div>
                                <p class="mt-4 text-sm font-semibold">Creator workspace</p>
                                <p class="mt-1 text-sm text-white/70">Profile, uploads, albums, and public presentation working together in one flow.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            @foreach($creatorSteps as $item)
                <article class="glass-card rounded-[1.8rem] p-6">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-primary/60">{{ $item['step'] }}</p>
                    <h2 class="mt-3 text-xl font-black tracking-tight text-gray-900">{{ $item['title'] }}</h2>
                    <p class="mt-3 text-sm leading-relaxed text-gray-600">{{ $item['body'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr),minmax(280px,0.72fr)]">
            <article class="glass-card rounded-[2rem] p-6 sm:p-8">
                <div class="inline-flex rounded-full bg-primary/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-primary-700">
                    What You Get
                </div>
                <h2 class="mt-4 text-2xl font-black tracking-tight text-gray-900">Everything needed to go from account to artist presence.</h2>
                <ul class="mt-5 space-y-3 text-sm text-gray-600">
                    @foreach($creatorHighlights as $point)
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary-700">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                            <span>{{ $point }}</span>
                        </li>
                    @endforeach
                </ul>
            </article>

            <article class="glass-card rounded-[2rem] p-6 sm:p-8">
                <div class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-700">
                    Best Next Step
                </div>
                <h2 class="mt-4 text-2xl font-black tracking-tight text-gray-900">Already have a listener account?</h2>
                <p class="mt-3 text-sm leading-relaxed text-gray-600">
                    You do not need a second login. Switch that account into artist access, create your profile, and continue into the upload workflow from there.
                </p>

                <div class="mt-5 rounded-[1.6rem] border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-sm font-semibold text-emerald-800">Upgrade path</p>
                    <p class="mt-2 text-sm text-emerald-700">
                        Visiting any artist-only page now routes listener accounts into a guided artist-upgrade flow instead of an unauthorized error page.
                    </p>
                </div>
            </article>
        </section>

        @if($showMonetizationPreview)
            <section class="glass-card rounded-[2rem] p-6 sm:p-8">
                <h2 class="text-2xl font-black tracking-tight text-gray-900">Future creator tools</h2>
                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-gray-600">
                    This section is reserved for future creator tooling and can be turned back on when that roadmap is ready.
                </p>
            </section>
        @endif
    </div>
</div>
