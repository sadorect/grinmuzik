<?php

use App\Models\ArtistProfile;
use App\Models\Genre;
use App\Support\ArtistAccess;
use App\Support\ArtistActivityNotifier;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('layouts.glass-app')] class extends Component
{
    #[Validate('required|string|min:2|max:80')]
    public string $stageName = '';

    #[Validate('nullable|string|max:500')]
    public string $bio = '';

    #[Validate('nullable|url|max:255')]
    public string $website = '';

    #[Validate('nullable|string|max:100')]
    public string $instagram = '';

    #[Validate('nullable|string|max:100')]
    public string $twitter = '';

    #[Validate('nullable|string|max:100')]
    public string $spotify = '';

    public array $selectedGenres = [];

    public ?string $intendedPath = null;

    public bool $showUpgradePrompt = false;

    public function mount(): void
    {
        $user = auth()->user();
        $this->intendedPath = ArtistAccess::sanitizeIntended(request()->query('intended'));

        if (! $user->isArtist()) {
            $this->showUpgradePrompt = true;

            return;
        }

        if ($user->artistProfile) {
            $this->redirect($this->intendedPath ?? route('artist.dashboard'), navigate: true);
        }
    }

    public function with(): array
    {
        return [
            'genres' => Genre::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }

    public function upgradeToArtist(): void
    {
        $user = auth()->user();

        ArtistAccess::promote($user);

        session()->flash('artist_upgrade_success', 'Artist access is now enabled. Finish your profile to unlock uploads and artist tools.');

        $this->redirect(route('artist.setup', array_filter([
            'intended' => $this->intendedPath,
        ])), navigate: true);
    }

    public function save(): void
    {
        $this->validate();

        $user = auth()->user();

        $slug = Str::slug($this->stageName);
        $base = $slug;
        $count = 1;

        while (ArtistProfile::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $count++;
        }

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => $this->stageName,
            'slug' => $slug,
            'bio' => $this->bio ?: null,
            'website' => $this->website ?: null,
            'instagram' => $this->instagram ?: null,
            'twitter' => $this->twitter ?: null,
            'spotify' => $this->spotify ?: null,
            'is_verified' => $user->hasVerifiedEmail(),
            'is_approved' => $user->hasVerifiedEmail(),
            'is_active' => true,
        ]);

        if ($this->selectedGenres !== []) {
            $profile->genres()->sync($this->selectedGenres);
        }

        app(ArtistActivityNotifier::class)->sendArtistProfileCreated($profile);

        $this->redirect($this->intendedPath ?? route('artist.dashboard'), navigate: true);
    }
};
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-xl space-y-6">
        @if(session('artist_upgrade_success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('artist_upgrade_success') }}
            </div>
        @endif

        @if($showUpgradePrompt)
            <section class="glass-card relative overflow-hidden rounded-[2rem] p-6 sm:p-8">
                <div class="absolute inset-x-0 top-0 h-40 bg-gradient-to-r from-primary/15 via-secondary/10 to-emerald-200/30"></div>
                <div class="absolute -right-10 top-8 h-32 w-32 rounded-full bg-primary/15 blur-3xl"></div>
                <div class="absolute -left-8 bottom-0 h-28 w-28 rounded-full bg-emerald-200/40 blur-3xl"></div>

                <div class="relative z-10">
                    <div class="mb-5 inline-flex h-16 w-16 items-center justify-center rounded-3xl bg-primary text-white shadow-lg">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                        </svg>
                    </div>

                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-primary/70">Artist Access Required</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight text-gray-900">This account is still set up as a listener.</h1>
                    <p class="mt-4 text-sm leading-relaxed text-gray-600 sm:text-base">
                        Uploads, album management, and artist dashboards are reserved for artist accounts. You can upgrade this account now and continue straight into your artist profile setup.
                    </p>

                    <div class="mt-6 grid gap-3 sm:grid-cols-3">
                        @foreach([
                            ['label' => 'Current mode', 'value' => 'Listener'],
                            ['label' => 'Upgrade time', 'value' => '1 step'],
                            ['label' => 'After upgrade', 'value' => 'Create profile'],
                        ] as $stat)
                            <div class="rounded-2xl border border-white/70 bg-white/80 px-4 py-3 shadow-sm">
                                <p class="text-lg font-black text-gray-900">{{ $stat['value'] }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-gray-400">{{ $stat['label'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 rounded-[1.6rem] border border-white/70 bg-white/85 p-5 shadow-sm">
                        <h2 class="text-sm font-semibold uppercase tracking-[0.22em] text-gray-500">What happens next</h2>
                        <ul class="mt-4 space-y-3 text-sm text-gray-600">
                            <li class="flex items-start gap-3">
                                <span class="mt-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary/10 text-primary">1</span>
                                <span>We enable artist access on this same account.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="mt-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary/10 text-primary">2</span>
                                <span>You create your public artist profile with your name, bio, and genres.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="mt-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary/10 text-primary">3</span>
                                <span>You continue into uploads and the rest of the artist tools without seeing a 403 page again.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button
                            wire:click="upgradeToArtist"
                            class="inline-flex items-center gap-2 rounded-full bg-primary px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500"
                        >
                            Enable Artist Access
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>

                        <a
                            href="{{ route('listener.dashboard') }}"
                            wire:navigate
                            class="inline-flex items-center gap-2 rounded-full border border-white/70 bg-white/85 px-5 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-white"
                        >
                            Stay in Listener Dashboard
                        </a>
                    </div>
                </div>
            </section>
        @else
            <div class="text-center mb-2">
                <div class="w-16 h-16 bg-primary-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Set Up Your Artist Profile</h1>
                <p class="text-gray-500 text-sm mt-1">Tell listeners who you are. You can update this anytime.</p>
            </div>

            <div class="glass-card rounded-2xl p-6">
                <form wire:submit="save" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Artist / Stage Name <span class="text-primary">*</span>
                        </label>
                        <input
                            wire:model="stageName"
                            type="text"
                            placeholder="How should fans know you?"
                            class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-gray-800 placeholder-gray-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30 transition">
                        @error('stageName')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Bio</label>
                        <textarea
                            wire:model="bio"
                            rows="3"
                            placeholder="Tell your story in a few sentences..."
                            class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-gray-800 placeholder-gray-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30 transition resize-none"></textarea>
                        @error('bio')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Genres (pick up to 3)</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($genres as $genre)
                                <label class="cursor-pointer">
                                    <input type="checkbox" wire:model="selectedGenres" value="{{ $genre->id }}" class="sr-only peer">
                                    <span class="inline-block px-3 py-1.5 rounded-full text-sm border border-gray-200 text-gray-600 peer-checked:bg-primary peer-checked:border-primary peer-checked:text-white hover:border-primary-300 hover:text-primary-600 transition">
                                        {{ $genre->name }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div x-data="{ open: false }">
                        <button type="button" @click="open = !open" class="flex items-center gap-2 text-sm text-gray-500 hover:text-primary transition">
                            <svg class="w-4 h-4 transition" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            Social links (optional)
                        </button>
                        <div x-show="open" x-collapse class="mt-3 space-y-3">
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">instagram.com/</span>
                                <input wire:model="instagram" type="text" placeholder="yourusername" class="w-full bg-white border border-gray-200 rounded-xl pl-32 pr-4 py-2.5 text-gray-800 placeholder-gray-400 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30 transition">
                            </div>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">twitter.com/</span>
                                <input wire:model="twitter" type="text" placeholder="yourusername" class="w-full bg-white border border-gray-200 rounded-xl pl-28 pr-4 py-2.5 text-gray-800 placeholder-gray-400 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30 transition">
                            </div>
                            <input wire:model="spotify" type="url" placeholder="Spotify profile URL" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-gray-800 placeholder-gray-400 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30 transition">
                            <input wire:model="website" type="url" placeholder="https://yourwebsite.com" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-gray-800 placeholder-gray-400 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30 transition">
                        </div>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 flex gap-2">
                        <svg class="w-4 h-4 text-yellow-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2Zm1 14h-2v-2h2v2Zm0-4h-2V7h2v5Z"/>
                        </svg>
                        <p class="text-yellow-700 text-xs leading-relaxed">
                            Verify your email to activate and approve your public artist profile automatically. An admin can still step in manually if needed.
                        </p>
                    </div>

                    <button type="submit" class="w-full bg-primary hover:bg-primary-600 text-white py-3 rounded-xl font-semibold transition shadow-sm">
                        <span wire:loading.remove>Create Profile</span>
                        <span wire:loading>Creating...</span>
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
