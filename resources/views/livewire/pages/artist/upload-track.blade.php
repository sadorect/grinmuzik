<?php

use App\Jobs\ProcessOversizedTrackUpload;
use App\Livewire\Concerns\ManagesReleaseSchedule;
use App\Models\Album;
use App\Models\Genre;
use App\Models\Track;
use App\Rules\ValidAudioUpload;
use App\Rules\ValidImageUpload;
use App\StagesLivewireUploads;
use App\Support\ArtistAccess;
use App\Support\ArtistActivityNotifier;
use App\Support\UploadLimits;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.glass-app')] class extends Component
{
    use ManagesReleaseSchedule;
    use StagesLivewireUploads;
    use WithFileUploads;

    public string $title = '';
    public string $description = '';
    public string $lyrics = '';
    public ?int $genre_id = null;
    public ?int $album_id = null;
    public ?string $mood = null;
    public int $track_number = 1;
    public bool $requires_donation = false;
    public string $donation_amount = '';

    public $audioFile = null;
    public $coverFile = null;

    public bool $audioOversize = false;
    public bool $coverOversize = false;
    public ?float $audioOversizeMb = null;
    public ?float $coverOversizeMb = null;

    public array $genres = [];
    public array $albums = [];
    public array $moods = [];
    public bool $paymentsEnabled = false;

    public function mount(): void
    {
        if (! auth()->user()->isArtist()) {
            $this->redirect(ArtistAccess::setupUrl(request()->getRequestUri()), navigate: true);

            return;
        }

        $profile = auth()->user()->artistProfile;

        if (! $profile) {
            $this->redirect(ArtistAccess::setupUrl(request()->getRequestUri()), navigate: true);

            return;
        }

        $this->genres = Genre::active()->orderBy('name')->get(['id', 'name', 'slug'])->toArray();
        $this->albums = $profile->albums()->orderByDesc('created_at')->get(['id', 'title'])->toArray();
        $this->moods = collect(Track::moodOptions())
            ->map(fn (string $label, string $slug) => ['slug' => $slug, 'label' => $label])
            ->values()
            ->all();
        $this->paymentsEnabled = SiteSetting::paymentsEnabled();
    }

    /** The chosen album, when it is waiting on a scheduled release. */
    public function scheduledAlbum(): ?Album
    {
        if (! $this->album_id) {
            return null;
        }

        $album = auth()->user()->artistProfile?->albums()->find($this->album_id);

        return $album?->is_scheduled ? $album : null;
    }

    public function suggestedMoodLabel(): ?string
    {
        if (! $this->genre_id) {
            return null;
        }

        $genre = collect($this->genres)->firstWhere('id', $this->genre_id);
        $suggestedMood = Track::suggestedMoodSlugForGenre($genre['slug'] ?? null);

        return $suggestedMood ? (Track::moodOptions()[$suggestedMood] ?? null) : null;
    }

    protected function rules(): array
    {
        $profileId = auth()->user()?->artistProfile?->id;

        $rules = [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'lyrics' => ['nullable', 'string', 'max:10000'],
            'genre_id' => ['nullable', 'exists:genres,id'],
            'album_id' => ['nullable', Rule::exists('albums', 'id')->where(fn ($query) => $query->where('artist_profile_id', $profileId))],
            'mood' => ['nullable', 'string', 'max:50'],
            'track_number' => ['nullable', 'integer', 'min:1'],
            ...$this->releaseScheduleRules(),
            'requires_donation' => ['boolean'],
            'donation_amount' => ['nullable', 'numeric', 'min:0.5', 'max:999'],
            'audioFile' => ['required', 'file', new ValidAudioUpload(), 'max:' . UploadLimits::audioKb()],
            'coverFile' => ['nullable', 'image', new ValidImageUpload(), 'max:' . UploadLimits::imageKb()],
        ];

        if ($this->requires_donation) {
            $rules['donation_amount'][] = 'required';
        }

        return $rules;
    }

    public function updatedAudioFile(): void
    {
        $this->audioOversize = false;
        $this->audioOversizeMb = null;

        if (! $this->audioFile) {
            return;
        }

        // Oversize: offer conversion instead of a hard error, but still confirm it's audio.
        if ($this->audioFile->getSize() > UploadLimits::audioKb() * 1024) {
            $this->resetErrorBag('audioFile');
            $this->validateOnly('audioFile', ['audioFile' => ['required', 'file', new ValidAudioUpload()]]);
            $this->audioOversize = true;
            $this->audioOversizeMb = round($this->audioFile->getSize() / 1048576, 1);

            return;
        }

        $this->validateOnly('audioFile');
    }

    public function updatedCoverFile(): void
    {
        $this->coverOversize = false;
        $this->coverOversizeMb = null;

        if (! $this->coverFile) {
            return;
        }

        if ($this->coverFile->getSize() > UploadLimits::imageKb() * 1024) {
            $this->resetErrorBag('coverFile');
            $this->validateOnly('coverFile', ['coverFile' => ['required', 'image', new ValidImageUpload()]]);
            $this->coverOversize = true;
            $this->coverOversizeMb = round($this->coverFile->getSize() / 1048576, 1);

            return;
        }

        $this->validateOnly('coverFile');
    }

    /**
     * Artist consented to automatic conversion: persist the uploads durably and
     * hand off to the background job, which converts, creates, and notifies.
     */
    public function confirmConversion(): void
    {
        $this->paymentsEnabled = SiteSetting::paymentsEnabled();

        if (! $this->paymentsEnabled) {
            $this->requires_donation = false;
        }

        if (! $this->audioOversize && ! $this->coverOversize) {
            return;
        }

        // Validate everything except the file-size caps — conversion handles size.
        $rules = $this->rules();
        $rules['audioFile'] = ['required', 'file', new ValidAudioUpload()];
        $rules['coverFile'] = ['nullable', 'image', new ValidImageUpload()];
        $this->validate($rules);

        $uuid = Str::uuid()->toString();
        $audioExt = strtolower($this->audioFile->getClientOriginalExtension() ?: 'mp3');
        $audioRel = $this->audioFile->storeAs('tmp/oversized-imports', $uuid . '.' . $audioExt, 'local');
        $audioOriginalName = $this->audioFile->getClientOriginalName();

        $coverRel = null;
        $coverOriginalName = null;

        if ($this->coverFile) {
            $coverExt = strtolower($this->coverFile->getClientOriginalExtension() ?: 'jpg');
            $coverRel = $this->coverFile->storeAs('tmp/oversized-imports', $uuid . '-cover.' . $coverExt, 'local');
            $coverOriginalName = $this->coverFile->getClientOriginalName();
        }

        $trackData = [
            'album_id' => $this->album_id,
            'genre_id' => $this->genre_id,
            'mood' => $this->mood,
            'title' => $this->title,
            'description' => $this->description,
            'lyrics' => $this->lyrics ?: null,
            'track_number' => $this->track_number ?: 1,
            ...$this->releaseAttributes(),
            'is_free' => ! ($this->paymentsEnabled && $this->requires_donation),
            'donation_amount' => ($this->paymentsEnabled && $this->requires_donation) ? (float) $this->donation_amount : 1.00,
        ];

        ProcessOversizedTrackUpload::dispatch(
            (int) auth()->id(),
            $trackData,
            $audioRel,
            $audioOriginalName,
            $coverRel,
            $coverOriginalName,
        );

        session()->flash('success', "We're converting your file and finishing the upload in the background. You'll be notified when it's ready.");
        $this->redirect(route('artist.tracks'));
    }

    public function cancelConversion(): void
    {
        $this->reset('audioFile', 'coverFile', 'audioOversize', 'coverOversize', 'audioOversizeMb', 'coverOversizeMb');
        $this->resetErrorBag(['audioFile', 'coverFile']);
    }

    protected function messages(): array
    {
        return [
            ...$this->releaseScheduleMessages(),
            'audioFile.max' => 'The audio file must not be greater than ' . UploadLimits::formatKilobytes(UploadLimits::audioKb()) . '.',
            'coverFile.max' => 'The cover image must not be greater than ' . UploadLimits::formatKilobytes(UploadLimits::imageKb()) . '.',
        ];
    }

    public function save(): void
    {
        $this->resetErrorBag('save');
        $this->paymentsEnabled = SiteSetting::paymentsEnabled();

        if (! $this->paymentsEnabled) {
            $this->requires_donation = false;
        }

        $this->validate();

        $profile = auth()->user()->artistProfile;
        $track = null;

        try {
            DB::transaction(function () use ($profile, &$track): void {
                $track = Track::create([
                    'user_id' => auth()->id(),
                    'artist_profile_id' => $profile->id,
                    'album_id' => $this->album_id,
                    'genre_id' => $this->genre_id,
                    'mood' => $this->mood,
                    'title' => $this->title,
                    'description' => $this->description,
                    'lyrics' => $this->lyrics ?: null,
                    'track_number' => $this->track_number ?: 1,
                    ...$this->releaseAttributes(),
                    'is_free' => ! ($this->paymentsEnabled && $this->requires_donation),
                    'donation_amount' => ($this->paymentsEnabled && $this->requires_donation) ? (float) $this->donation_amount : 1.00,
                ]);

                $audioExt = strtolower($this->audioFile->getClientOriginalExtension());
                $audioFileName = (Str::slug($this->title) ?: 'track') . '.' . $audioExt;
                $audioDuration = $this->extractAudioDuration($this->audioFile);

                $this->addStagedMedia($track, $this->audioFile, 'audio', $audioFileName, 'audioFile');

                if ($audioDuration > 0) {
                    $track->update(['duration' => $audioDuration]);
                }

                if ($this->coverFile) {
                    $coverExt = strtolower($this->coverFile->getClientOriginalExtension());

                    $this->addStagedMedia($track, $this->coverFile, 'cover', 'cover.' . $coverExt, 'coverFile');
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            if ($track?->exists) {
                $track->delete();
            }

            $this->addError('save', "We couldn't upload your track right now. Please review the files and try again.");

            return;
        }

        app(ArtistActivityNotifier::class)->sendTrackUploaded($track->fresh(['artistProfile.user', 'genre', 'album']));

        session()->flash('success', $this->releaseFlash('Track', $track->title) ?: "Track \"{$track->title}\" uploaded successfully!");
        $this->redirect(route('artist.tracks'));
    }
};
?>

<div class="max-w-3xl mx-auto px-4 py-8">

    {{-- Upload type selector modal: shown when arriving from the homepage (?src=home) --}}
    <div
        x-data="{
            open: false,
            closeAndClean() {
                this.open = false;
                history.replaceState({}, '', window.location.pathname);
            }
        }"
        x-init="
            const params = new URLSearchParams(window.location.search);
            if (params.get('src') === 'home') {
                open = true;
                $nextTick(() => $refs.firstOption && $refs.firstOption.focus());
            }
        "
        x-show="open"
        x-on:keydown.escape.window="open && closeAndClean()"
        class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center"
        aria-modal="true"
        role="dialog"
        aria-labelledby="upload-selector-title"
        style="display: none;"
    >
        {{-- Backdrop --}}
        <div
            class="absolute inset-0 bg-black/60 backdrop-blur-sm"
            x-transition:enter="transition-opacity ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="closeAndClean()"
        ></div>

        {{-- Modal panel --}}
        <div
            class="glass-modal relative z-10 w-full max-w-lg"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95"
        >
            {{-- Icon + Heading --}}
            <div class="mb-7 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10">
                    <svg class="h-7 w-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"/>
                    </svg>
                </div>
                <h2 id="upload-selector-title" class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">
                    Upload Your Music
                </h2>
                <p class="mt-1.5 text-sm text-gray-500">What would you like to upload today?</p>
            </div>

            {{-- Option Cards --}}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">

                {{-- Single Track --}}
                <button
                    type="button"
                    x-ref="firstOption"
                    @click="closeAndClean()"
                    class="group flex flex-col items-center gap-4 rounded-2xl border-2 border-white/50 bg-white/60 p-7 text-center shadow-sm transition duration-200 hover:-translate-y-1 hover:border-primary/40 hover:bg-white hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-primary/50 focus:ring-offset-2 dark:border-white/10 dark:bg-slate-800/60 dark:hover:bg-slate-800"
                >
                    <span class="text-5xl leading-none" aria-hidden="true">🎵</span>
                    <div>
                        <p class="font-bold text-gray-900 transition-colors group-hover:text-primary dark:text-white">Single Track</p>
                        <p class="mt-1 text-sm text-gray-500">Upload one song for release.</p>
                    </div>
                    <span class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary opacity-0 transition-opacity group-hover:opacity-100">
                        Choose
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                        </svg>
                    </span>
                </button>

                {{-- Album / EP --}}
                <a
                    href="{{ route('artist.create-album') }}"
                    wire:navigate
                    class="group flex flex-col items-center gap-4 rounded-2xl border-2 border-white/50 bg-white/60 p-7 text-center shadow-sm transition duration-200 hover:-translate-y-1 hover:border-primary/40 hover:bg-white hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-primary/50 focus:ring-offset-2 dark:border-white/10 dark:bg-slate-800/60 dark:hover:bg-slate-800"
                >
                    <span class="text-5xl leading-none" aria-hidden="true">💿</span>
                    <div>
                        <p class="font-bold text-gray-900 transition-colors group-hover:text-primary dark:text-white">Album / EP</p>
                        <p class="mt-1 text-sm text-gray-500">Upload multiple tracks as a project.</p>
                    </div>
                    <span class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary opacity-0 transition-opacity group-hover:opacity-100">
                        Choose
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                        </svg>
                    </span>
                </a>
            </div>

            {{-- Cancel --}}
            <div class="mt-6 text-center">
                <a href="{{ route('home') }}"
                   wire:navigate
                   class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 transition hover:text-gray-800 dark:hover:text-gray-200">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Go Back
                </a>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3 mb-8">
        <a href="{{ route('artist.tracks') }}" wire:navigate class="text-gray-500 hover:text-gray-700 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-2xl font-bold text-gray-800">Upload Track</h1>
    </div>

    @if(session('success'))
        <div class="mb-6 px-4 py-3 bg-green-100 text-green-700 rounded-xl text-sm">{{ session('success') }}</div>
    @endif

    @if($errors->has('save'))
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ $errors->first('save') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-6" enctype="multipart/form-data">
        <div
            class="glass-card rounded-2xl p-6 space-y-4"
        x-data="{ drag: false, uploading: false, progress: 0, done: false }"
        x-on:livewire-upload-start="if ($event.detail.property !== 'audioFile') return; uploading = true; progress = 0; done = false"
        x-on:livewire-upload-progress="progress = $event.detail.progress"
        x-on:livewire-upload-finish="if ($event.detail.property !== 'audioFile') return; uploading = false; progress = 100; done = true"
        x-on:livewire-upload-error="if ($event.detail.property !== 'audioFile') return; uploading = false; done = false"
        x-on:livewire-upload-cancel="if ($event.detail.property !== 'audioFile') return; uploading = false; progress = 0; done = false"
        >
            <h2 class="font-semibold text-gray-700">Audio File <span class="text-red-500">*</span></h2>
            <div
                x-on:dragover.prevent="drag = true"
                x-on:dragleave.prevent="drag = false"
                x-on:drop.prevent="drag = false"
                :class="drag ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                class="border-2 border-dashed rounded-xl p-8 text-center transition cursor-pointer"
                onclick="document.getElementById('audioInput').click()"
            >
                <svg class="mx-auto w-10 h-10 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"/></svg>
                @if($audioFile)
                    <p class="text-sm font-medium text-green-600">Selected: {{ $audioFile->getClientOriginalName() }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ round($audioFile->getSize() / 1048576, 1) }} MB</p>
                @else
                    <p class="text-sm font-medium text-gray-600">Drop your audio file here or <span class="text-red-500">browse</span></p>
                    <p class="text-xs text-gray-400 mt-1">MP3, WAV, FLAC, OGG, AAC, M4A - Max {{ UploadLimits::formatKilobytes(UploadLimits::audioKb()) }}</p>
                @endif
                <input id="audioInput" wire:model="audioFile" type="file" accept=".mp3,.wav,.flac,.ogg,.aac,.m4a,audio/*" class="sr-only">
            </div>
            <div x-show="uploading" class="space-y-2">
                <div class="flex items-center justify-between text-xs font-medium text-gray-500">
                    <span>Uploading audio file...</span>
                    <span x-text="`${progress}%`"></span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-gray-200">
                    <div class="h-full rounded-full bg-red-500 transition-all duration-200" :style="`width: ${progress}%`"></div>
                </div>
            </div>
            <div x-show="done && !uploading" class="flex items-center gap-2 text-sm text-green-600 font-medium">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                File received — click <span class="font-semibold ml-1">Upload Track</span> to save.
            </div>
            <x-input-error :messages="$errors->get('audioFile')" />
        </div>

        <div class="glass-card rounded-2xl p-6 space-y-5">
            <h2 class="font-semibold text-gray-700">Track Details</h2>

            <div>
                <x-input-label for="title" value="Title *" />
                <x-text-input wire:model="title" id="title" class="block mt-1 w-full" type="text" placeholder="Track title" />
                <x-input-error :messages="$errors->get('title')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="description" value="Description" />
                <textarea wire:model="description" id="description" rows="3"
                    class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-red-400 focus:ring-red-400 text-sm resize-none"
                    placeholder="What's this track about?"></textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-1" />
                <p class="mt-1 text-xs text-gray-400">A short blurb — put the words of the song in the Lyrics box below.</p>
            </div>

            <div>
                <x-input-label for="lyrics" value="Lyrics" />
                <textarea wire:model="lyrics" id="lyrics" rows="8"
                    class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-red-400 focus:ring-red-400 text-sm resize-y font-mono"
                    placeholder="Paste your lyrics here — one line per line, blank lines between sections."></textarea>
                <x-input-error :messages="$errors->get('lyrics')" class="mt-1" />
                <p class="mt-1 text-xs text-gray-400">Optional. Lyrics get their own shareable section on the track page.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="genre_id" value="Genre" />
                    <select wire:model="genre_id" id="genre_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-red-400 focus:ring-red-400 text-sm">
                        <option value="">No genre</option>
                        @foreach($genres as $g)
                            <option value="{{ $g['id'] }}">{{ $g['name'] }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('genre_id')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="album_id" value="Album" />
                    <select wire:model.live="album_id" id="album_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-red-400 focus:ring-red-400 text-sm">
                        <option value="">Standalone single</option>
                        @foreach($albums as $a)
                            <option value="{{ $a['id'] }}">{{ $a['title'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="mood" value="Mood" />
                    <select wire:model="mood" id="mood" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-red-400 focus:ring-red-400 text-sm">
                        <option value="">No mood</option>
                        @foreach($moods as $m)
                            <option value="{{ $m['slug'] }}">{{ $m['label'] }}</option>
                        @endforeach
                    </select>
                    @if(!$mood && $this->suggestedMoodLabel())
                        <p class="mt-1 text-xs text-gray-400">Suggested from genre: {{ $this->suggestedMoodLabel() }}</p>
                    @else
                        <p class="mt-1 text-xs text-gray-400">Helps browse and discovery rails place the track correctly.</p>
                    @endif
                    <x-input-error :messages="$errors->get('mood')" class="mt-1" />
                </div>
            </div>

            <div class="w-32">
                <x-input-label for="track_number" value="Track #" />
                <x-text-input wire:model="track_number" id="track_number" class="block mt-1 w-full" type="number" min="1" />
            </div>
        </div>

        <div
            class="glass-card rounded-2xl p-6 space-y-3"
            x-data="{ uploading: false, progress: 0 }"
            x-on:livewire-upload-start="if ($event.detail.property !== 'coverFile') return; uploading = true; progress = 0"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
            x-on:livewire-upload-finish="if ($event.detail.property !== 'coverFile') return; uploading = false; progress = 100"
            x-on:livewire-upload-error="if ($event.detail.property !== 'coverFile') return; uploading = false"
            x-on:livewire-upload-cancel="if ($event.detail.property !== 'coverFile') return; uploading = false; progress = 0"
        >
            <h2 class="font-semibold text-gray-700">Cover Art <span class="text-xs text-gray-400 font-normal">(optional)</span></h2>
            <div class="flex items-center gap-5">
                @if($coverFile)
                    <img src="{{ $coverFile->temporaryUrl() }}" class="w-20 h-20 rounded-xl object-cover ring-2 ring-red-400" alt="">
                @else
                    <div class="w-20 h-20 rounded-xl bg-gray-200 flex items-center justify-center text-gray-400 shrink-0">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                @endif
                <div>
                    <input wire:model="coverFile" type="file" accept="image/jpeg,image/png,image/webp"
                           class="text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-red-50 file:text-red-600 hover:file:bg-red-100">
                    <p class="text-xs text-gray-400 mt-1">JPG, PNG, WEBP - recommended 1:1, up to {{ UploadLimits::formatKilobytes(UploadLimits::imageKb()) }}</p>
                </div>
            </div>
            <div x-show="uploading" class="space-y-2">
                <div class="flex items-center justify-between text-xs font-medium text-gray-500">
                    <span>Uploading cover art...</span>
                    <span x-text="`${progress}%`"></span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-gray-200">
                    <div class="h-full rounded-full bg-red-500 transition-all duration-200" :style="`width: ${progress}%`"></div>
                </div>
            </div>
            <x-input-error :messages="$errors->get('coverFile')" />
        </div>

        <div class="glass-card rounded-2xl p-6 space-y-4">
            <h2 class="font-semibold text-gray-700">Release</h2>

            @php($scheduledAlbum = $this->scheduledAlbum())
            <x-release-schedule
                :album-release-at="$scheduledAlbum?->scheduled_release_at"
                :album-title="$scheduledAlbum?->title"
            />

            @if($paymentsEnabled)
                <label class="flex items-center gap-3 cursor-pointer">
                    <input wire:model.live="requires_donation" type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary-400">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Require donation to unlock full track</p>
                        <p class="text-xs text-gray-400">Listeners pay once to stream and download</p>
                    </div>
                </label>

                @if($requires_donation)
                    <div class="ml-7 w-44">
                        <x-input-label for="donation_amount" value="Unlock Price (USD) *" />
                        <div class="relative mt-1">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">$</span>
                            <x-text-input wire:model="donation_amount" id="donation_amount" class="block w-full pl-7" type="number" min="0.5" step="0.5" placeholder="2.00" />
                        </div>
                        <x-input-error :messages="$errors->get('donation_amount')" class="mt-1" />
                    </div>
                @endif
            @endif
        </div>

        <div class="pb-4 space-y-3">
            @if($audioOversize || $coverOversize)
                {{-- Oversize consent: offer automatic background conversion instead of rejecting --}}
                <div class="glass-card rounded-xl p-4 border border-amber-300/60 bg-amber-50/70 dark:bg-amber-500/10">
                    <p class="font-semibold text-amber-800 dark:text-amber-300 text-sm">This file is larger than the upload limit</p>
                    <ul class="mt-1 text-sm text-amber-700 dark:text-amber-200/90 list-disc list-inside space-y-0.5">
                        @if($audioOversize)
                            <li>Audio is {{ $audioOversizeMb }} MB — limit is {{ \App\Support\UploadLimits::formatKilobytes(\App\Support\UploadLimits::audioKb()) }}.</li>
                        @endif
                        @if($coverOversize)
                            <li>Cover image is {{ $coverOversizeMb }} MB — limit is {{ \App\Support\UploadLimits::formatKilobytes(\App\Support\UploadLimits::imageKb()) }}.</li>
                        @endif
                    </ul>
                    <p class="mt-2 text-sm text-amber-700 dark:text-amber-200/90">
                        We can automatically convert it to a supported size in the background and notify you when it’s ready.
                        Prefer not to? Cancel and upload a smaller file instead — nothing is saved until conversion succeeds.
                    </p>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" wire:click="cancelConversion" wire:loading.attr="disabled"
                            class="glass-btn glass-btn-hover px-5 py-2.5 rounded-xl text-sm font-medium">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmConversion" wire:target="confirmConversion" wire:loading.attr="disabled"
                            class="glass-btn-primary glass-btn-primary-hover px-6 py-2.5 rounded-xl text-sm font-semibold">
                        <span wire:loading.remove wire:target="confirmConversion">Convert &amp; upload</span>
                        <span wire:loading wire:target="confirmConversion">Starting…</span>
                    </button>
                </div>
            @else
                <div class="flex justify-end gap-3">
                    <a href="{{ route('artist.tracks') }}" wire:navigate
                       class="glass-btn glass-btn-hover px-5 py-2.5 rounded-xl text-sm font-medium">
                        Cancel
                    </a>
                    <button type="submit"
                            class="glass-btn-primary glass-btn-primary-hover px-6 py-2.5 rounded-xl text-sm font-semibold"
                            wire:loading.attr="disabled"
                            wire:target="audioFile,coverFile,save">
                        <span wire:loading.remove wire:target="audioFile,coverFile,save">Upload Track</span>
                        <span wire:loading wire:target="audioFile,coverFile">Uploading files...</span>
                        <span wire:loading wire:target="save">Saving track...</span>
                    </button>
                </div>
            @endif
        </div>
    </form>

    <div wire:loading wire:target="save"
         class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="glass-card rounded-2xl p-8 text-center space-y-4 max-w-xs mx-4 shadow-2xl">
            <svg class="mx-auto w-12 h-12 text-red-500 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <div>
                <p class="font-semibold text-gray-800">Saving to cloud…</p>
                <p class="text-sm text-gray-500 mt-1">Please don’t close this page.</p>
            </div>
        </div>
    </div>
</div>
