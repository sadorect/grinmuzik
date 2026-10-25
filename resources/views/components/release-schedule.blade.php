@props([
    'nowLabel' => 'Publish now',
    'nowHint' => 'Visible to listeners as soon as you save.',
    'draftHint' => 'Only you can see it until you publish.',
    'wasLive' => false,
    'albumReleaseAt' => null,
    'albumTitle' => null,
])

{{--
    Release choice for ManagesReleaseSchedule. The picker works in the artist's
    local time; Alpine hands Livewire the matching UTC instant in `release_at`.
--}}
<div
    x-data="{
        mode: $wire.entangle('release_mode'),
        utc: $wire.entangle('release_at'),
        local: '',
        pad(n) { return String(n).padStart(2, '0') },
        toLocal(iso) {
            const d = iso ? new Date(iso) : null;
            if (!d || isNaN(d)) return '';
            return `${d.getFullYear()}-${this.pad(d.getMonth() + 1)}-${this.pad(d.getDate())}T${this.pad(d.getHours())}:${this.pad(d.getMinutes())}`;
        },
        sync() {
            const d = this.local ? new Date(this.local) : null;
            this.utc = d && !isNaN(d) ? d.toISOString() : null;
        },
        useAlbumTime(iso) { this.mode = 'scheduled'; this.local = this.toLocal(iso); this.sync(); },
        get zone() { return Intl.DateTimeFormat().resolvedOptions().timeZone },
    }"
    x-init="local = toLocal(utc)"
    {{ $attributes->class(['space-y-3']) }}
>
    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
        @foreach([
            'now' => [$nowLabel, $nowHint],
            'scheduled' => ['Schedule for later', 'Goes live automatically at the time you pick.'],
            'draft' => ['Save as draft', $draftHint],
        ] as $value => [$label, $hint])
            <label
                class="flex cursor-pointer items-start gap-2.5 rounded-xl border p-3 transition"
                :class="mode === '{{ $value }}' ? 'border-red-400 bg-red-50/70 dark:bg-red-500/10' : 'border-gray-200 hover:border-gray-300 dark:border-white/10'"
            >
                <input type="radio" value="{{ $value }}" x-model="mode" class="mt-0.5 border-gray-300 text-red-500 focus:ring-red-400">
                <span>
                    <span class="block text-sm font-medium text-gray-700">{{ $label }}</span>
                    <span class="block text-xs text-gray-400">{{ $hint }}</span>
                </span>
            </label>
        @endforeach
    </div>

    <div x-show="mode === 'scheduled'" x-cloak class="space-y-3 rounded-xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-400/20 dark:bg-amber-500/10">
        <div class="sm:w-72">
            <x-input-label for="release_at_local" value="Release date & time" />
            <input
                id="release_at_local"
                type="datetime-local"
                x-model="local"
                x-on:change="sync()"
                x-on:input="sync()"
                :min="toLocal(new Date().toISOString())"
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-400 focus:ring-red-400"
            >
            <p class="mt-1 text-xs text-gray-500">Your local time (<span x-text="zone"></span>). Hidden from listeners until then.</p>
            <x-input-error :messages="$errors->get('release_at')" class="mt-1" />
        </div>

        @if($albumReleaseAt)
            <div class="flex flex-wrap items-center gap-2 text-xs text-amber-800 dark:text-amber-200">
                <span>"{{ $albumTitle }}" is scheduled for <x-local-time :at="$albumReleaseAt" class="font-semibold" />.</span>
                <button type="button" x-on:click="useAlbumTime(@js($albumReleaseAt->toIso8601ZuluString()))"
                        class="rounded-full border border-amber-300 px-2.5 py-0.5 font-semibold hover:bg-amber-100 dark:border-amber-400/30 dark:hover:bg-amber-500/20">
                    Release with the album
                </button>
            </div>
        @endif

        {{ $slot }}
    </div>

    @if($wasLive)
        <p x-show="mode !== 'now'" x-cloak class="text-xs font-medium text-amber-700 dark:text-amber-300">
            This is live right now. Saving will take it down until <span x-text="mode === 'scheduled' ? 'the scheduled time' : 'you publish it again'"></span>.
        </p>
    @endif

    <x-input-error :messages="$errors->get('release_mode')" />
</div>
