<?php

use App\Models\Album;
use App\Models\Genre;
use App\Models\Track;
use App\StagesLivewireUploads;
use App\Support\ArtistAccess;
use App\Support\UploadLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.glass-app')] class extends Component
{
    use StagesLivewireUploads;
    use WithFileUploads;

    public Album $album;
    public string $title = '';
    public string $description = '';
    public string $type = 'album';
    public ?int $genre_id = null;
    public string $release_date = '';
    public bool $is_published = false;
    public $coverFile = null;
    public array $genres = [];
    public string $trackSearch = '';
    public array $selectedTrackIds = [];
    public bool $confirmReassign = false;

    public function mount(Album $album): void
    {
        if (! auth()->user()->isArtist()) {
            $this->redirect(ArtistAccess::setupUrl(request()->getRequestUri()), navigate: true);

            return;
        }

        $profile = auth()->user()->artistProfile;
        abort_unless($profile && $album->artist_profile_id === $profile->id, 403);

        $this->album = $album->load('tracks');
        $this->title = $album->title;
        $this->description = $album->description ?? '';
        $this->type = $album->type;
        $this->genre_id = $album->genre_id;
        $this->release_date = $album->release_date?->format('Y-m-d') ?? '';
        $this->is_published = (bool) $album->is_published;
        $this->selectedTrackIds = $album->tracks->sortBy('track_number')->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $this->genres = Genre::active()->orderBy('name')->get(['id', 'name'])->toArray();
    }

    protected function artistProfile()
    {
        return auth()->user()->artistProfile;
    }

    protected function selectedTracksCollection()
    {
        $profile = $this->artistProfile();
        $selectedIds = collect($this->selectedTrackIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($selectedIds->isEmpty()) {
            return collect();
        }

        $tracks = Track::query()
            ->where('artist_profile_id', $profile->id)
            ->whereIn('id', $selectedIds)
            ->with(['album:id,title'])
            ->get();

        $order = array_flip($selectedIds->all());

        return $tracks->sortBy(fn (Track $track) => $order[$track->id] ?? PHP_INT_MAX)->values();
    }

    protected function tracksRequiringReassignment()
    {
        return $this->selectedTracksCollection()
            ->filter(fn (Track $track) => ! is_null($track->album_id) && $track->album_id !== $this->album->id);
    }

    public function with(): array
    {
        $profile = $this->artistProfile();
        $search = trim($this->trackSearch);

        return [
            'availableTracks' => Track::query()
                ->where('artist_profile_id', $profile->id)
                ->with(['album:id,title', 'genre:id,name'])
                ->when($search !== '', function ($query) use ($search): void {
                    $term = '%' . $search . '%';

                    $query->where(function ($builder) use ($term): void {
                        $builder
                            ->where('title', 'like', $term)
                            ->orWhere('description', 'like', $term)
                            ->orWhereHas('album', fn ($albumQuery) => $albumQuery->where('title', 'like', $term));
                    });
                })
                ->orderByRaw('CASE WHEN album_id IS NULL THEN 0 WHEN album_id = '.$this->album->id.' THEN 1 ELSE 2 END')
                ->orderByDesc('created_at')
                ->get(),
            'selectedTracks' => $this->selectedTracksCollection(),
            'tracksRequiringReassignment' => $this->tracksRequiringReassignment(),
        ];
    }

    public function save(): void
    {
        $profile = $this->artistProfile();

        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(['album', 'ep', 'single'])],
            'genre_id' => ['nullable', 'exists:genres,id'],
            'release_date' => ['nullable', 'date'],
            'is_published' => ['boolean'],
            'coverFile' => ['nullable', 'image', 'max:' . UploadLimits::imageKb()],
            'selectedTrackIds' => ['array'],
            'selectedTrackIds.*' => ['integer', Rule::exists('tracks', 'id')->where(fn ($query) => $query->where('artist_profile_id', $profile->id))],
            'confirmReassign' => ['boolean'],
        ]);

        $selectedTracks = $this->selectedTracksCollection();
        $tracksRequiringReassignment = $selectedTracks->filter(fn (Track $track) => ! is_null($track->album_id) && $track->album_id !== $this->album->id);

        if ($this->type === 'single' && $selectedTracks->count() > 1) {
            $this->addError('selectedTrackIds', 'Singles can only contain one track.');

            return;
        }

        if ($tracksRequiringReassignment->isNotEmpty() && ! $this->confirmReassign) {
            $this->addError('confirmReassign', 'Confirm that the selected tracks should move out of their current albums before saving.');

            return;
        }

        DB::transaction(function () use ($selectedTracks): void {
            $this->album->update([
                'title' => $this->title,
                'description' => $this->description,
                'type' => $this->type,
                'genre_id' => $this->genre_id,
                'release_date' => $this->release_date ?: null,
                'is_published' => $this->is_published,
            ]);

            if ($this->coverFile) {
                $this->addStagedMedia(
                    $this->album,
                    $this->coverFile,
                    'cover',
                    'cover.' . strtolower($this->coverFile->getClientOriginalExtension())
                );
            }

            Track::query()
                ->where('album_id', $this->album->id)
                ->whereNotIn('id', $selectedTracks->pluck('id')->all())
                ->update(['album_id' => null]);

            foreach ($selectedTracks->values() as $index => $track) {
                $track->update([
                    'album_id' => $this->album->id,
                    'track_number' => $index + 1,
                ]);
            }
        });

        session()->flash('success', "Album \"{$this->album->title}\" updated!");
        $this->redirect(route('artist.albums'), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-6 px-4 py-8">
    <div class="flex items-center gap-3">
        <a href="{{ route('artist.albums') }}" wire:navigate class="text-gray-500 transition hover:text-gray-700">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Manage Album</h1>
            <p class="text-sm text-gray-500">Update release details, reorder attached tracks, or reassign uploaded songs into this album.</p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6" enctype="multipart/form-data">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,0.95fr),minmax(0,1.05fr)]">
            <div class="glass-card space-y-5 rounded-2xl p-6">
                <div>
                    <x-input-label for="title" value="Album Title *" />
                    <x-text-input wire:model="title" id="title" class="mt-1 block w-full" type="text" />
                    <x-input-error :messages="$errors->get('title')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Type" />
                    <div class="mt-2 flex gap-3">
                        @foreach(['album' => 'Album', 'ep' => 'EP', 'single' => 'Single'] as $value => $label)
                            <label class="flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-xl border-2 py-2 text-sm font-medium transition {{ $type === $value ? 'border-red-500 bg-red-50 text-red-600' : 'border-gray-200 text-gray-600 hover:border-red-300' }}">
                                <input type="radio" wire:model.live="type" value="{{ $value }}" class="sr-only">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea wire:model="description" id="description" rows="4" class="mt-1 block w-full resize-none rounded-md border-gray-300 text-sm shadow-sm focus:border-red-400 focus:ring-red-400"></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="genre_id" value="Genre" />
                        <select wire:model="genre_id" id="genre_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-400 focus:ring-red-400">
                            <option value="">— None —</option>
                            @foreach($genres as $genre)
                                <option value="{{ $genre['id'] }}">{{ $genre['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="release_date" value="Release Date" />
                        <x-text-input wire:model="release_date" id="release_date" class="mt-1 block w-full" type="date" />
                    </div>
                </div>

                <div>
                    <x-input-label value="Cover Art" />
                    <div class="mt-2 flex items-center gap-4">
                        @if($coverFile)
                            <img src="{{ $coverFile->temporaryUrl() }}" class="h-20 w-20 rounded-xl object-cover ring-2 ring-red-400" alt="">
                        @elseif($album->getCoverUrl())
                            <img src="{{ $album->getCoverUrl() }}" class="h-20 w-20 rounded-xl object-cover" alt="">
                        @else
                            <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-xl bg-gray-200 text-gray-400">
                                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14"/></svg>
                            </div>
                        @endif
                        <input wire:model="coverFile" type="file" accept="image/jpeg,image/png,image/webp" class="text-sm text-gray-600 file:mr-3 file:rounded-full file:border-0 file:bg-red-50 file:px-4 file:py-1.5 file:text-xs file:font-semibold file:text-red-600 hover:file:bg-red-100">
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Leave blank to keep the current cover.</p>
                    <x-input-error :messages="$errors->get('coverFile')" class="mt-1" />
                </div>

                <label class="flex cursor-pointer items-center gap-3">
                    <input wire:model="is_published" type="checkbox" class="rounded border-gray-300 text-red-500 focus:ring-red-400">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Release is public</p>
                        <p class="text-xs text-gray-400">Upcoming releases can stay public while their date is still in the future.</p>
                    </div>
                </label>
            </div>

            <div class="glass-card rounded-2xl p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800">Attached Tracks</h2>
                        <p class="mt-1 text-sm text-gray-500">Keep current tracks, add new uploads, or remove songs that should become standalone again.</p>
                    </div>
                    <div class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                        {{ count($selectedTrackIds) }} selected
                    </div>
                </div>

                <div class="mt-4">
                    <x-text-input wire:model.live.debounce.250ms="trackSearch" type="search" class="block w-full" placeholder="Search uploaded tracks or album assignments" />
                </div>

                @if($tracksRequiringReassignment->isNotEmpty())
                    <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        <p class="font-semibold">Some selected tracks currently belong to a different album.</p>
                        <p class="mt-1">Saving will move them into {{ $album->title }} and remove them from their current release.</p>
                        <div class="mt-3 space-y-1 text-xs text-amber-700">
                            @foreach($tracksRequiringReassignment as $track)
                                <p>{{ $track->title }} &middot; currently in {{ $track->album?->title }}</p>
                            @endforeach
                        </div>
                        <label class="mt-3 flex items-start gap-3">
                            <input wire:model="confirmReassign" type="checkbox" class="mt-1 rounded border-amber-300 text-amber-600 focus:ring-amber-400">
                            <span>I confirm these tracks should be reassigned into this album.</span>
                        </label>
                        <x-input-error :messages="$errors->get('confirmReassign')" class="mt-2" />
                    </div>
                @endif

                <x-input-error :messages="$errors->get('selectedTrackIds')" class="mt-4" />

                <div class="mt-4 max-h-[34rem] space-y-3 overflow-y-auto pr-1">
                    @forelse($availableTracks as $track)
                        <label class="flex cursor-pointer gap-3 rounded-2xl border border-white/60 bg-white/75 p-4 transition hover:border-red-200 hover:bg-white">
                            <input wire:model.live="selectedTrackIds" type="checkbox" value="{{ $track->id }}" class="mt-1 rounded border-gray-300 text-red-500 focus:ring-red-400">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-sm font-semibold text-gray-800">{{ $track->title }}</p>
                                    @if($track->genre)
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500">{{ $track->genre->name }}</span>
                                    @endif
                                    @if($track->album_id === $album->id)
                                        <span class="rounded-full bg-primary-100 px-2 py-0.5 text-[11px] font-medium text-primary-700">In this album</span>
                                    @elseif($track->album)
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">Assigned elsewhere</span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500">Unassigned</span>
                                    @endif
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                    <span>{{ $track->formatted_duration }}</span>
                                    @if($track->album)
                                        <span>&middot; currently in {{ $track->album->title }}</span>
                                    @endif
                                </div>
                            </div>
                        </label>
                    @empty
                        <div class="rounded-2xl border border-dashed border-gray-200 px-6 py-10 text-center text-sm text-gray-500">
                            No uploaded tracks matched this search.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        @if($selectedTracks->isNotEmpty())
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold text-gray-800">Track Order</h2>
                <p class="mt-1 text-sm text-gray-500">Track numbers are rewritten sequentially from this selection order when you save.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($selectedTracks as $index => $track)
                        <div class="rounded-2xl border border-white/60 bg-white/75 px-4 py-3 text-sm text-gray-700">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-400">Track {{ $index + 1 }}</p>
                            <p class="mt-1 font-semibold text-gray-800">{{ $track->title }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex justify-end gap-3">
            <a href="{{ route('artist.albums') }}" wire:navigate class="glass-btn glass-btn-hover rounded-xl px-5 py-2.5 text-sm font-medium">Cancel</a>
            <button type="submit" class="glass-btn-primary glass-btn-primary-hover rounded-xl px-6 py-2.5 text-sm font-semibold" wire:loading.attr="disabled">
                <span wire:loading.remove>Save Album</span>
                <span wire:loading>Saving…</span>
            </button>
        </div>
    </form>
</div>