<?php

use App\StagesLivewireUploads;
use App\Support\UploadLimits;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Genre;

new #[Layout('layouts.glass-app')] class extends Component
{
    use StagesLivewireUploads;
    use WithFileUploads;

    public string $stageName = '';
    public string $bio = '';
    public string $website = '';
    public string $instagram = '';
    public string $twitter = '';
    public string $youtube = '';
    public string $spotify = '';

    public array $selectedGenres = [];

    public bool $removeExistingAvatar = false;
    public bool $removeExistingBanner = false;

    public $avatar = null;
    public $banner = null;

    public function mount(): void
    {
        $user = auth()->user();

        abort_unless($user->isArtist(), 403);

        $profile = $user->artistProfile;

        if (! $profile) {
            $this->redirect(route('artist.setup'), navigate: true);

            return;
        }

        $this->stageName = $profile->stage_name ?? '';
        $this->bio = $profile->bio ?? '';
        $this->website = $profile->website ?? '';
        $this->instagram = $profile->instagram ?? '';
        $this->twitter = $profile->twitter ?? '';
        $this->youtube = $profile->youtube ?? '';
        $this->spotify = $profile->spotify ?? '';
        $this->selectedGenres = $profile->genres()->pluck('genres.id')->map(fn ($id) => (int) $id)->all();
    }

    public function with(): array
    {
        $profile = auth()->user()->artistProfile?->loadMissing('user');

        return [
            'profile' => $profile,
            'genres' => Genre::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }

    protected function rules(): array
    {
        return [
            'stageName' => ['required', 'string', 'min:2', 'max:80'],
            'bio' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:100'],
            'twitter' => ['nullable', 'string', 'max:100'],
            'youtube' => ['nullable', 'url', 'max:255'],
            'spotify' => ['nullable', 'url', 'max:255'],
            'selectedGenres' => ['nullable', 'array', 'max:5'],
            'selectedGenres.*' => ['integer', 'exists:genres,id'],
            'avatar' => ['nullable', 'image', 'max:' . UploadLimits::imageKb()],
            'banner' => ['nullable', 'image', 'max:' . UploadLimits::heroImageKb()],
        ];
    }

    public function updatedAvatar(): void
    {
        $this->removeExistingAvatar = false;
        $this->validateOnly('avatar');
    }

    public function updatedBanner(): void
    {
        $this->removeExistingBanner = false;
        $this->validateOnly('banner');
    }

    public function save(): void
    {
        $validated = $this->validate();
        $profile = auth()->user()->artistProfile;

        DB::transaction(function () use ($profile, $validated): void {
            $profile->update([
                'stage_name' => $validated['stageName'],
                'bio' => blank($validated['bio']) ? null : $validated['bio'],
                'website' => blank($validated['website']) ? null : $validated['website'],
                'instagram' => blank($validated['instagram']) ? null : $validated['instagram'],
                'twitter' => blank($validated['twitter']) ? null : $validated['twitter'],
                'youtube' => blank($validated['youtube']) ? null : $validated['youtube'],
                'spotify' => blank($validated['spotify']) ? null : $validated['spotify'],
            ]);

            $profile->genres()->sync($validated['selectedGenres'] ?? []);

            if ($this->removeExistingAvatar) {
                $profile->clearMediaCollection('avatar');
            }

            if ($this->removeExistingBanner) {
                $profile->clearMediaCollection('banner');
            }

            if ($this->avatar) {
                $this->addStagedMedia(
                    $profile,
                    $this->avatar,
                    'avatar',
                    'avatar.' . strtolower($this->avatar->getClientOriginalExtension()),
                    'avatar'
                );
            }

            if ($this->banner) {
                $this->addStagedMedia(
                    $profile,
                    $this->banner,
                    'banner',
                    'banner.' . strtolower($this->banner->getClientOriginalExtension()),
                    'banner'
                );
            }
        });

        $this->reset('avatar', 'banner');
        $this->removeExistingAvatar = false;
        $this->removeExistingBanner = false;

        $this->dispatch('artist-profile-saved');
    }
};
?>

<div class="mx-auto max-w-6xl px-4 py-8">
    @if($profile)
        <section class="glass-card rounded-[2rem] p-6 sm:p-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-primary/70">Artist Profile</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight text-gray-900 sm:text-4xl">Edit your public profile</h1>
                    <p class="mt-2 max-w-2xl text-sm text-gray-500 sm:text-base">
                        Update the photo, stage name, bio, social links, and discovery details listeners see across the app.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('artist.page', $profile) }}" wire:navigate
                       class="rounded-full border border-white/70 bg-white/80 px-5 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-white">
                        View Public Page
                    </a>
                    <a href="{{ route('profile') }}" wire:navigate
                       class="rounded-full border border-white/70 bg-white/80 px-5 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-white">
                        Account Settings
                    </a>
                </div>
            </div>
        </section>

        <form wire:submit="save" enctype="multipart/form-data" class="mt-8 grid gap-8 xl:grid-cols-[minmax(280px,340px),minmax(0,1fr)]">
            <div class="space-y-6">
                <div class="glass-card rounded-[2rem] p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-primary/70">Profile Photo</p>
                    <div class="mt-4 flex flex-col items-center gap-4 text-center">
                        <div class="h-36 w-36 overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-primary-100 to-primary-200 ring-4 ring-white/80 shadow-xl">
                            @if($avatar)
                                <img src="{{ $avatar->temporaryUrl() }}" alt="Avatar preview" class="h-full w-full object-cover">
                            @elseif(!$removeExistingAvatar && $profile->getAvatarMediaUrl())
                                <img src="{{ $profile->getAvatarMediaUrl() }}" alt="{{ $profile->avatar_alt }}" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-5xl font-black text-primary-400">
                                    {{ strtoupper(substr($profile->display_name, 0, 1)) }}
                                </div>
                            @endif
                        </div>

                        <div class="w-full space-y-3">
                            <label class="block rounded-2xl border border-dashed border-gray-300 bg-white/80 px-4 py-4 text-sm text-gray-600 transition hover:border-primary-300 hover:bg-white">
                                <span class="block font-semibold text-gray-800">Upload new photo</span>
                                <span class="mt-1 block text-xs text-gray-500">JPG, PNG, or WebP up to {{ UploadLimits::formatKilobytes(UploadLimits::imageKb()) }}</span>
                                <input wire:model="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only">
                            </label>
                            @error('avatar')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror

                            @if(!$avatar && $profile->getFirstMediaUrl('avatar'))
                                <label class="flex items-center justify-center gap-2 rounded-2xl border border-white/60 bg-white/70 px-4 py-3 text-sm text-gray-600">
                                    <input type="checkbox" wire:model.live="removeExistingAvatar" class="rounded border-gray-300 text-primary focus:ring-primary">
                                    Remove current artist photo
                                </label>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="glass-card rounded-[2rem] p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-primary/70">Banner</p>
                    <div class="mt-4 space-y-4">
                        <div class="aspect-[4/3] overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-primary-700 via-primary-800 to-primary-900 shadow-xl">
                            @if($banner)
                                <img src="{{ $banner->temporaryUrl() }}" alt="Banner preview" class="h-full w-full object-cover">
                            @elseif(!$removeExistingBanner && $profile->getBannerUrl())
                                <img src="{{ $profile->getBannerUrl() }}" alt="{{ $profile->banner_alt }}" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-end bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.28),transparent_42%),linear-gradient(135deg,rgba(255,255,255,0.18),transparent)] p-5">
                                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-white/70">Artist Banner Preview</p>
                                </div>
                            @endif
                        </div>

                        <label class="block rounded-2xl border border-dashed border-gray-300 bg-white/80 px-4 py-4 text-sm text-gray-600 transition hover:border-primary-300 hover:bg-white">
                            <span class="block font-semibold text-gray-800">Upload new banner</span>
                            <span class="mt-1 block text-xs text-gray-500">JPG, PNG, or WebP up to {{ UploadLimits::formatKilobytes(UploadLimits::heroImageKb()) }}</span>
                            <input wire:model="banner" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only">
                        </label>
                        @error('banner')
                            <p class="text-xs text-red-500">{{ $message }}</p>
                        @enderror

                        @if(!$banner && $profile->getFirstMediaUrl('banner'))
                            <label class="flex items-center gap-2 rounded-2xl border border-white/60 bg-white/70 px-4 py-3 text-sm text-gray-600">
                                <input type="checkbox" wire:model.live="removeExistingBanner" class="rounded border-gray-300 text-primary focus:ring-primary">
                                Remove current banner
                            </label>
                        @endif
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="glass-card rounded-[2rem] p-6 sm:p-8">
                    <div class="grid gap-5">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">
                                Artist / Stage Name <span class="text-primary">*</span>
                            </label>
                            <input
                                wire:model="stageName"
                                type="text"
                                placeholder="How should fans know you?"
                                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-800 placeholder-gray-400 transition focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30">
                            @error('stageName')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Bio</label>
                            <textarea
                                wire:model="bio"
                                rows="5"
                                placeholder="Tell listeners who you are, what you make, and what they should expect."
                                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-800 placeholder-gray-400 transition focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30"></textarea>
                            @error('bio')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Genres</label>
                            <div class="flex flex-wrap gap-2">
                                @foreach($genres as $genre)
                                    <label class="cursor-pointer">
                                        <input type="checkbox"
                                               wire:model="selectedGenres"
                                               value="{{ $genre->id }}"
                                               class="peer sr-only">
                                        <span class="inline-block rounded-full border border-gray-200 px-3 py-1.5 text-sm text-gray-600 transition hover:border-primary-300 hover:text-primary-600 peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white">
                                            {{ $genre->name }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('selectedGenres')
                                <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                            @error('selectedGenres.*')
                                <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="glass-card rounded-[2rem] p-6 sm:p-8">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-primary/70">Links & Reach</p>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Website</label>
                            <input wire:model="website" type="url" placeholder="https://yourwebsite.com"
                                   class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-800 placeholder-gray-400 transition focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30">
                            @error('website')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Instagram</label>
                            <input wire:model="instagram" type="text" placeholder="yourusername"
                                   class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-800 placeholder-gray-400 transition focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30">
                            @error('instagram')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Twitter / X</label>
                            <input wire:model="twitter" type="text" placeholder="yourusername"
                                   class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-800 placeholder-gray-400 transition focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30">
                            @error('twitter')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">YouTube</label>
                            <input wire:model="youtube" type="url" placeholder="https://youtube.com/@yourchannel"
                                   class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-800 placeholder-gray-400 transition focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30">
                            @error('youtube')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Spotify</label>
                            <input wire:model="spotify" type="url" placeholder="https://open.spotify.com/artist/..."
                                   class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-800 placeholder-gray-400 transition focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30">
                            @error('spotify')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit"
                            class="rounded-full bg-primary px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500">
                        <span wire:loading.remove wire:target="save,avatar,banner">Save Artist Profile</span>
                        <span wire:loading wire:target="save,avatar,banner">Saving...</span>
                    </button>

                    <x-action-message on="artist-profile-saved">
                        Profile updated.
                    </x-action-message>
                </div>
            </div>
        </form>
    @endif
</div>
