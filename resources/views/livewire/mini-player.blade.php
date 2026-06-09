{{--
    Mini Player — persistent bottom bar
    Audio lives in window.GrinPlayer (JS singleton) — survives wire:navigate DOM swaps.
    Alpine init()/destroy() re-bind/unbind event listeners on each navigation cycle.
--}}
<div
    x-data="miniPlayer()"
    x-init="init()"
    @destroyed="destroy()"
    x-on:player-track-loaded.window="onTrackLoaded($event.detail)"
    x-on:player-queue-add.window="onQueueAdd($event.detail)"
    @download-unavailable.window="showStatusToast($event.detail.message || 'Download unavailable.')"
    @start-download.window="startBackgroundDownload($event.detail)"
    class="fixed bottom-0 left-0 right-0 z-50"
    x-show="hasActiveTrack"
    x-cloak
    style="display:none"
>
    {{-- ── Queue Panel (slides up above the bar) ───────────────────────────── --}}
    <div
        x-show="showQueue && hasActiveTrack && queueTracks.length > 1"
        x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        @click.outside="showQueue = false"
        class="absolute bottom-full w-full sm:w-96 sm:right-4 sm:left-auto left-0 mb-1"
        style="display:none"
    >
        <div class="glass-panel rounded-t-2xl sm:rounded-2xl shadow-2xl border border-white/40 overflow-hidden">
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-white/30">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10"/>
                    </svg>
                    <span class="text-sm font-semibold text-gray-800">Up Next</span>
                    <span class="text-xs text-gray-400 tabular-nums" x-text="`${queueTracks.length} track${queueTracks.length !== 1 ? 's' : ''}`"></span>
                </div>
                <button @click="clearQueue()"
                        x-show="queueTracks.length > 0"
                        class="text-xs text-gray-400 hover:text-primary-500 transition">
                    Clear all
                </button>
            </div>

            {{-- Track list --}}
            <div class="max-h-72 overflow-y-auto overscroll-contain">
                <template x-if="queueTracks.length === 0">
                    <div class="px-4 py-8 text-center text-gray-400 text-sm">
                        Queue is empty.<br>
                        <span class="text-xs">Click a track to play, or use + to add.</span>
                    </div>
                </template>
                <template x-for="(t, idx) in queueTracks" :key="t.id">
                    <div
                        @click="jumpTo(idx)"
                        class="flex items-center gap-3 px-4 py-2.5 cursor-pointer transition hover:bg-white/50"
                        :class="idx === queueIndex ? 'bg-primary-50/70' : ''"
                    >
                        <div class="w-5 text-center shrink-0">
                            <template x-if="idx === queueIndex">
                                <span class="inline-flex gap-0.5 items-end h-4">
                                    <span class="w-0.5 bg-primary-500 rounded-full animate-bounce" style="height:60%;animation-delay:0ms"></span>
                                    <span class="w-0.5 bg-primary-500 rounded-full animate-bounce" style="height:100%;animation-delay:150ms"></span>
                                    <span class="w-0.5 bg-primary-500 rounded-full animate-bounce" style="height:40%;animation-delay:75ms"></span>
                                </span>
                            </template>
                            <template x-if="idx !== queueIndex">
                                <span class="text-xs text-gray-400 tabular-nums" x-text="idx + 1"></span>
                            </template>
                        </div>
                        <img
                            :src="t.cover || '/images/placeholder-track.svg'"
                            class="w-9 h-9 rounded-lg object-cover shrink-0 ring-1 ring-white/30"
                            :alt="t.title"
                        >
                        <div class="flex-1 min-w-0">
                            <a
                                :href="t.url || '#'"
                                wire:navigate
                                @click.stop
                                class="block text-xs font-semibold truncate transition hover:text-primary"
                                :class="idx === queueIndex ? 'text-primary-600' : 'text-gray-800'"
                                x-text="t.title"
                            ></a>
                            <a
                                :href="t.artist_url || '#'"
                                wire:navigate
                                @click.stop
                                class="block text-xs text-gray-400 truncate transition hover:text-primary"
                                x-text="t.artist"
                            ></a>
                        </div>
                        <button @click.stop="removeFromQueue(idx)"
                                class="text-gray-300 hover:text-primary-500 transition shrink-0"
                                title="Remove">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ── Queue-added toast ────────────────────────────────────────────────── --}}
    <div
        x-show="queueToast"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute bottom-full mb-2 right-16 bg-gray-800 text-white text-xs px-3 py-1.5 rounded-full shadow-lg pointer-events-none"
        style="display:none"
    >
        Added to queue
    </div>

    <div
        x-show="statusToastMessage"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-xs px-3 py-1.5 rounded-full shadow-lg pointer-events-none"
        style="display:none"
        x-text="statusToastMessage"
    ></div>

    <div
        x-show="giftOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @keydown.escape.window="closeGiftModal()"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/55 p-4 backdrop-blur-sm"
        style="display:none"
    >
        <div class="w-full max-w-md overflow-hidden rounded-[1.75rem] border border-white/10 bg-gray-950 shadow-2xl">
            <div class="flex items-start justify-between border-b border-white/10 px-5 py-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/45">Support</p>
                    <h3 class="mt-1 text-lg font-semibold text-white">Gift artist</h3>
                </div>
                <button type="button" @click="closeGiftModal()" class="rounded-full p-2 text-white/50 transition hover:bg-white/5 hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="space-y-5 px-5 py-5">
                <div class="flex items-center gap-3 rounded-2xl bg-white/5 px-3 py-3">
                    <img
                        :src="track?.cover || '/images/placeholder-track.svg'"
                        :alt="track?.title || 'Track cover'"
                        class="h-12 w-12 rounded-xl object-cover"
                    >
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-white" x-text="track?.title || 'Current track'"></p>
                        <p class="truncate text-xs text-white/55" x-text="track?.artist || 'Artist'"></p>
                    </div>
                </div>

                <div class="space-y-3">
                    <label class="block text-sm font-medium text-white/85">Amount</label>
                    <div class="grid grid-cols-5 gap-2">
                        <template x-for="amount in giftPresetOptions" :key="amount">
                            <button
                                type="button"
                                @click="selectGiftAmount(amount)"
                                class="rounded-xl border px-3 py-2 text-sm font-semibold transition"
                                :class="Number(giftAmount) === amount ? 'border-primary-400 bg-primary-500 text-white' : 'border-white/10 bg-white/5 text-white/75 hover:border-white/20 hover:bg-white/10'"
                                x-text="`$${amount}`"
                            ></button>
                        </template>
                    </div>
                    <input
                        x-model="giftAmount"
                        type="number"
                        min="0.5"
                        step="0.5"
                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition placeholder:text-white/25 focus:border-primary-400 focus:bg-white/10"
                        placeholder="Custom amount"
                    >
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-white/85">Message</label>
                    <textarea
                        x-model="giftMessage"
                        rows="3"
                        maxlength="280"
                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition placeholder:text-white/25 focus:border-primary-400 focus:bg-white/10"
                        placeholder="Optional note"
                    ></textarea>
                </div>

                <div x-show="giftLoading" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-5 text-center text-sm text-white/55" style="display:none">
                    Loading secure payment form...
                </div>

                <div x-show="giftPrepared" x-ref="giftPaymentEl" class="rounded-2xl border border-white/10 bg-white px-3 py-3" style="display:none"></div>

                <p x-show="giftError" x-text="giftError" class="text-sm text-rose-300" style="display:none"></p>

                <div class="flex gap-3">
                    <button
                        type="button"
                        x-show="giftPrepared"
                        @click="resetGiftPayment()"
                        class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white/75 transition hover:bg-white/10"
                        style="display:none"
                    >
                        Change amount
                    </button>

                    <button
                        type="button"
                        x-show="!giftPrepared"
                        @click="prepareGiftPayment()"
                        :disabled="giftLoading"
                        class="flex-1 rounded-2xl bg-primary px-4 py-3 text-sm font-semibold text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Continue
                    </button>

                    <button
                        type="button"
                        x-show="giftPrepared"
                        @click="submitGift()"
                        :disabled="giftProcessing || giftLoading"
                        class="flex-1 rounded-2xl bg-primary px-4 py-3 text-sm font-semibold text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                        style="display:none"
                    >
                        <span x-show="!giftProcessing">Send gift</span>
                        <span x-show="giftProcessing" style="display:none">Processing...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div
        x-show="hasActiveTrack"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-y-full opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-cloak
        style="display: none;"
    >
        <div
            x-show="mobileExpanded && isMobileViewport"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-6"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-6"
            @keydown.escape.window="toggleMobilePlayer(false)"
            class="fixed inset-0 z-[55] sm:hidden"
            style="display:none; padding-top: calc(env(safe-area-inset-top) + 1.25rem); padding-bottom: calc(env(safe-area-inset-bottom) + 1.5rem);"
        >
            <div class="absolute inset-0 overflow-hidden bg-gradient-to-b from-white via-gray-100 to-gray-200 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
                <div
                    class="absolute inset-0 scale-110 bg-cover bg-center opacity-20 blur-3xl dark:opacity-25"
                    :style="track?.cover ? `background-image: url('${track.cover}')` : ''"
                ></div>
                <div class="absolute inset-0 bg-gradient-to-b from-white/80 via-white/88 to-gray-100/96 dark:from-slate-950/70 dark:via-slate-950/88 dark:to-slate-950"></div>
            </div>

            <div class="relative h-full overflow-y-auto px-5 text-gray-900 dark:text-white">
                <div class="mx-auto flex min-h-full w-full max-w-sm flex-col">
                    <div class="shrink-0 flex items-center justify-between">
                        <button
                            type="button"
                            @click="toggleMobilePlayer(false)"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-black/5 bg-white/75 text-gray-700 backdrop-blur-xl transition hover:bg-white/95 hover:text-gray-900 dark:border-white/10 dark:bg-white/10 dark:text-white/80 dark:hover:bg-white/15 dark:hover:text-white"
                            title="Collapse player"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 9l7 7 7-7"/>
                            </svg>
                        </button>

                        <div class="text-center">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-gray-500 dark:text-white/45">Now playing</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-white/60" x-text="queue.length > 1 ? `${queueIndex + 1} of ${queue.length}` : 'Single track'"></p>
                        </div>

                        <div class="h-11 w-11"></div>
                    </div>

                    <div class="flex flex-1 min-h-0 flex-col pb-4 pt-3">
                        <div class="flex min-h-0 flex-1 items-center justify-center py-2">
                            <div class="flex flex-col items-center justify-center gap-3">
                                <div
                                    class="overflow-hidden rounded-[2rem] border border-black/5 bg-white/70 shadow-2xl shadow-gray-900/10 backdrop-blur-xl dark:border-white/10 dark:bg-white/10 dark:shadow-black/30"
                                    style="width:min(68vw, 17rem, 31vh); height:min(68vw, 17rem, 31vh);"
                                >
                                    <img
                                        :src="track?.cover || '/images/placeholder-track.svg'"
                                        :alt="track?.title"
                                        class="h-full w-full object-cover"
                                    >
                                </div>

                                <p class="text-[11px] font-semibold uppercase tracking-[0.34em] text-gray-500/80 animate-[pulse_7s_ease-in-out_infinite] dark:text-white/45">
                                    GrinMuzik
                                </p>
                            </div>
                        </div>

                        <div x-show="hasActiveTrack" class="mt-3 grid shrink-0 grid-cols-4 gap-3" style="display:none">
                            <button
                                type="button"
                                @click="$wire.likeToggle()"
                                class="flex min-h-16 flex-col items-center justify-center rounded-[1.35rem] border border-black/5 bg-white/70 px-2 py-3 text-center text-gray-700 backdrop-blur-xl transition hover:bg-white/90 hover:text-gray-900 dark:border-white/10 dark:bg-white/10 dark:text-white/75 dark:hover:bg-white/15 dark:hover:text-white"
                                :title="$wire.liked ? 'Unlike' : 'Like'"
                            >
                                <svg :class="$wire.liked ? 'h-5 w-5 fill-current text-primary' : 'h-5 w-5 text-gray-700 dark:text-white/75'" viewBox="0 0 24 24" :fill="$wire.liked ? 'currentColor' : 'none'" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                                <span class="mt-2 max-w-full truncate text-[10px] font-semibold uppercase tracking-[0.14em] leading-tight">Like</span>
                                <span class="mt-1 max-w-full truncate text-[10px] text-gray-500 leading-tight dark:text-white/45" x-text="Number($wire.likesCount) > 0 ? $wire.likesCount : 'Save'"></span>
                            </button>

                            <button
                                type="button"
                                @click="shareTrack()"
                                class="flex min-h-16 flex-col items-center justify-center rounded-[1.35rem] border border-black/5 bg-white/70 px-2 py-3 text-center text-gray-700 backdrop-blur-xl transition hover:bg-white/90 hover:text-gray-900 dark:border-white/10 dark:bg-white/10 dark:text-white/75 dark:hover:bg-white/15 dark:hover:text-white"
                                :title="shareCopied ? 'Link copied!' : 'Share track'"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                </svg>
                                <span class="mt-2 max-w-full truncate text-[10px] font-semibold uppercase tracking-[0.14em] leading-tight">Share</span>
                                <span class="mt-1 max-w-full truncate text-[10px] text-gray-500 leading-tight dark:text-white/45" x-text="shareCopied ? 'Copied' : 'Track'"></span>
                            </button>

                            <button
                                type="button"
                                wire:click="downloadTrack"
                                class="flex min-h-16 flex-col items-center justify-center rounded-[1.35rem] border border-black/5 bg-white/70 px-2 py-3 text-center text-gray-700 backdrop-blur-xl transition hover:bg-white/90 hover:text-gray-900 dark:border-white/10 dark:bg-white/10 dark:text-white/75 dark:hover:bg-white/15 dark:hover:text-white"
                                title="Download"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                <span class="mt-2 max-w-full truncate text-[10px] font-semibold uppercase tracking-[0.14em] leading-tight">Download</span>
                                <span class="mt-1 max-w-full truncate text-[10px] text-gray-500 leading-tight dark:text-white/45" x-text="new Intl.NumberFormat().format(track?.downloads_count ?? 0)"></span>
                            </button>

                            <div class="flex min-h-16 flex-col items-center justify-center rounded-[1.35rem] border border-black/5 bg-white/70 px-2 py-3 text-center text-gray-700 backdrop-blur-xl dark:border-white/10 dark:bg-white/10 dark:text-white/75">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.868v4.264a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/>
                                </svg>
                                <span class="mt-2 max-w-full truncate text-[10px] font-semibold uppercase tracking-[0.14em] leading-tight">Plays</span>
                                <span class="mt-1 max-w-full truncate text-[10px] text-gray-500 leading-tight dark:text-white/45" x-text="new Intl.NumberFormat().format(track?.play_count ?? 0)"></span>
                            </div>
                        </div>

                        <div class="mt-3 shrink-0 pb-1">
                            <div class="grid grid-cols-[2rem_1fr_2rem] items-start">
                                <div></div>
                                <div class="min-w-0 text-center">
                                    <a
                                        :href="track?.url || '#'"
                                        wire:navigate
                                        class="block truncate text-xl font-semibold tracking-tight text-gray-900 transition hover:text-primary dark:text-white dark:hover:text-primary-300"
                                        x-text="track?.title"
                                    ></a>
                                    <a
                                        :href="track?.artist_url || '#'"
                                        wire:navigate
                                        class="mt-1.5 block truncate text-sm text-gray-500 transition hover:text-primary dark:text-white/60 dark:hover:text-primary-300"
                                        x-text="track?.artist"
                                    ></a>
                                    <button
                                        type="button"
                                        x-show="track?.can_gift_artist"
                                        @click="openGiftModal()"
                                        class="mt-3 inline-flex items-center rounded-full border border-primary-100 bg-primary-50 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-primary-700 transition hover:border-primary-200 hover:bg-primary-100 dark:border-primary-400/30 dark:bg-primary-500/15 dark:text-primary-200 dark:hover:border-primary-300/40 dark:hover:bg-primary-500/20"
                                        style="display:none"
                                    >
                                        Gift artist
                                    </button>
                                </div>

                                {{-- Floating volume button --}}
                                <div
                                    x-data="{ volOpen: false }"
                                    @click.outside="volOpen = false"
                                    class="relative flex justify-end pt-1"
                                >
                                    <button
                                        type="button"
                                        @click.stop="volOpen = !volOpen"
                                        class="p-1 text-gray-400 transition hover:text-gray-700 dark:text-white/35 dark:hover:text-white/75"
                                        :title="muted || volume === 0 ? 'Unmute' : 'Volume'"
                                    >
                                        <template x-if="muted || volume === 0">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.547 3.062A.75.75 0 0110 3.75v12.5a.75.75 0 01-1.264.546L4.703 13H3.167a.75.75 0 01-.7-.48A6.985 6.985 0 012 10c0-.887.165-1.737.468-2.52a.75.75 0 01.7-.48h1.535l4.033-3.796a.75.75 0 01.811-.142zM14.78 7.22a.75.75 0 00-1.06 1.06L14.94 9.5l-1.22 1.22a.75.75 0 001.06 1.06l1.22-1.22 1.22 1.22a.75.75 0 101.06-1.06L17.06 9.5l1.22-1.22a.75.75 0 10-1.06-1.06L16 8.44l-1.22-1.22z"/></svg>
                                        </template>
                                        <template x-if="!(muted || volume === 0)">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.547 3.062A.75.75 0 0110 3.75v12.5a.75.75 0 01-1.264.546L4.703 13H3.167a.75.75 0 01-.7-.48A6.985 6.985 0 012 10c0-.887.165-1.737.468-2.52a.75.75 0 01.7-.48h1.535l4.033-3.796a.75.75 0 01.811-.142zM13.28 6.22a.75.75 0 10-1.06 1.06A3.5 3.5 0 0113.5 10a3.5 3.5 0 01-1.28 2.72.75.75 0 101.06 1.06A5 5 0 0015 10a5 5 0 00-1.72-3.78zM16.72 4.22a.75.75 0 00-1.06 1.06A6.5 6.5 0 0117.5 10a6.5 6.5 0 01-1.84 4.72.75.75 0 101.06 1.06A8 8 0 0019 10a8 8 0 00-2.28-5.78z"/></svg>
                                        </template>
                                    </button>

                                    {{-- Volume slider popover --}}
                                    <div
                                        x-show="volOpen"
                                        x-transition:enter="transition ease-out duration-150 origin-top-right"
                                        x-transition:enter-start="opacity-0 scale-95"
                                        x-transition:enter-end="opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-100 origin-top-right"
                                        x-transition:leave-start="opacity-100 scale-100"
                                        x-transition:leave-end="opacity-0 scale-95"
                                        class="absolute right-0 top-full z-20 mt-1 w-36 rounded-2xl border border-black/5 bg-white/90 px-3 py-2.5 shadow-lg backdrop-blur-xl dark:border-white/10 dark:bg-gray-900/85"
                                        style="display: none;"
                                    >
                                        <input
                                            type="range"
                                            min="0"
                                            max="100"
                                            x-model="volume"
                                            @input="setVolume()"
                                            class="h-1.5 w-full cursor-pointer rounded-full accent-primary-500"
                                            aria-label="Volume"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 shrink-0 rounded-[1.75rem] border border-black/5 bg-white/75 px-4 py-4 backdrop-blur-xl dark:border-white/10 dark:bg-white/10">
                            <div class="flex items-center gap-3">
                                <span class="w-10 text-right text-xs tabular-nums text-gray-500 dark:text-white/55" x-text="formatTime(currentTime)"></span>
                                <div class="group relative h-2.5 flex-1 cursor-pointer rounded-full bg-gray-300/70 dark:bg-white/15" @click="seek($event)">
                                    <div class="h-full rounded-full bg-primary-400 transition-all" :style="`width: ${progress}%`"></div>
                                    <div class="absolute top-1/2 h-4 w-4 -translate-y-1/2 rounded-full border-2 border-white bg-primary-400 shadow-lg shadow-primary-900/30 transition" :style="`left: calc(${progress}% - 8px)`"></div>
                                </div>
                                <span class="w-10 text-xs tabular-nums text-gray-500 dark:text-white/55" x-text="formatTime(duration)"></span>
                            </div>

                            <div class="mt-6 flex items-center justify-center gap-4">
                                <button
                                    type="button"
                                    @click="toggleShuffle()"
                                    :aria-pressed="shuffleEnabled"
                                    :class="shuffleEnabled ? 'border-primary-200 bg-primary-50 text-primary-600 dark:border-primary-400/40 dark:bg-primary-500/15 dark:text-primary-300' : 'text-gray-400 dark:text-white/35'"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-black/5 bg-white/70 transition hover:text-gray-900 dark:border-white/10 dark:bg-white/5 dark:hover:text-white"
                                    :title="shuffleEnabled ? 'Shuffle on — tap for sequential' : 'Sequential — tap to shuffle'"
                                >
                                    {{-- Sequential: two rows each with a right-pointing chevron --}}
                                    <template x-if="!shuffleEnabled">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8h12M3 16h12M13 5l4 3-4 3M13 13l4 3-4 3"/>
                                        </svg>
                                    </template>
                                    {{-- Shuffle: two crossing arrows --}}
                                    <template x-if="shuffleEnabled">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6H7L17 16H21M17 12L21 16L17 20M3 18H7L17 8H21M17 4L21 8L17 12"/>
                                        </svg>
                                    </template>
                                </button>

                                <button
                                    type="button"
                                    @click="playPrev()"
                                    :class="canPlayPrev ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-white/30 cursor-default'"
                                    class="inline-flex h-12 w-12 items-center justify-center rounded-full border border-black/5 bg-white/70 transition dark:border-white/10 dark:bg-white/5"
                                    title="Previous"
                                >
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M7.75 5.75a.75.75 0 00-1.5 0v12.5a.75.75 0 001.5 0V5.75Zm9.97.297a.75.75 0 00-1.017-.267l-6.25 3.75a2.25 2.25 0 000 3.86l6.25 3.75a.75.75 0 10.771-1.287l-6.25-3.75a.75.75 0 010-1.286l6.25-3.75a.75.75 0 00.246-1.02Z"/></svg>
                                </button>

                                <button
                                    type="button"
                                    @click="togglePlay"
                                    class="inline-flex h-20 w-20 items-center justify-center rounded-full bg-primary-500 text-white shadow-2xl shadow-primary-900/40 transition active:scale-95"
                                    :title="playing ? 'Pause' : 'Play'"
                                >
                                    <template x-if="!playing">
                                        <svg class="ml-1 h-9 w-9" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                                    </template>
                                    <template x-if="playing">
                                        <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 20 20"><path d="M5.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75A.75.75 0 007.25 3h-1.5zM12.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75a.75.75 0 00-.75-.75h-1.5z"/></svg>
                                    </template>
                                </button>

                                <button
                                    type="button"
                                    @click="playNext()"
                                    :class="canPlayNext ? 'text-gray-900 dark:text-white' : 'text-gray-300 dark:text-white/30 cursor-default'"
                                    class="inline-flex h-12 w-12 items-center justify-center rounded-full border border-black/5 bg-white/70 transition dark:border-white/10 dark:bg-white/5"
                                    title="Next"
                                >
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M16.25 5.75a.75.75 0 011.5 0v12.5a.75.75 0 01-1.5 0V5.75Zm-9.97.297a.75.75 0 011.017-.267l6.25 3.75a2.25 2.25 0 010 3.86l-6.25 3.75a.75.75 0 11-.771-1.287l6.25-3.75a.75.75 0 000-1.286l-6.25-3.75a.75.75 0 01-.246-1.02Z"/></svg>
                                </button>

                                <button
                                    type="button"
                                    @click="cycleRepeatMode()"
                                    :aria-pressed="repeatMode !== 'off'"
                                    :class="repeatMode !== 'off' ? 'border-primary-200 bg-primary-50 text-primary-600 dark:border-primary-400/40 dark:bg-primary-500/15 dark:text-primary-300' : 'text-gray-400 dark:text-white/35'"
                                    class="relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-black/5 bg-white/70 transition hover:text-gray-900 dark:border-white/10 dark:bg-white/5 dark:hover:text-white"
                                    :title="repeatTitle"
                                >
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M13.28 2.97a.75.75 0 011.06 0l1.97 1.97a.75.75 0 010 1.06l-1.97 1.97a.75.75 0 11-1.06-1.06l.7-.69H6.75A2.25 2.25 0 004.5 8.47v.28a.75.75 0 01-1.5 0v-.28a3.75 3.75 0 013.75-3.75h7.23l-.7-.69a.75.75 0 010-1.06Z"/>
                                        <path d="M5.66 10.97a.75.75 0 011.06 0l.69.69h7.34A2.25 2.25 0 0017 9.41v-.16a.75.75 0 011.5 0v.16a3.75 3.75 0 01-3.75 3.75H7.41l.69.69a.75.75 0 11-1.06 1.06l-1.97-1.97a.75.75 0 010-1.06l1.97-1.97a.75.75 0 011.06 0 .75.75 0 010 1.06l-.69.69h.01-.01Z"/>
                                    </svg>
                                    <span
                                        x-show="repeatMode !== 'off'"
                                        x-text="repeatMode === 'track' ? '1' : 'ALL'"
                                        class="absolute -right-1 -top-1 rounded-full bg-primary-500 px-1.5 py-0.5 text-[9px] font-bold uppercase leading-none text-white"
                                        style="display:none"
                                    ></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mx-auto w-full max-w-sm space-y-5 pb-6 pt-4">
                        <div class="mt-5 overflow-hidden rounded-[1.75rem] border border-black/5 bg-white/75 backdrop-blur-xl dark:border-white/10 dark:bg-white/10">
                            <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-white/45">Queue</p>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-white/70" x-text="`${queueTracks.length} track${queueTracks.length === 1 ? '' : 's'} ready`"></p>
                                </div>
                                <button
                                    type="button"
                                    @click="clearQueue()"
                                    x-show="queueTracks.length > 0"
                                    class="rounded-full border border-black/5 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-600 transition hover:bg-white/90 hover:text-gray-900 dark:border-white/10 dark:text-white/60 dark:hover:bg-white/10 dark:hover:text-white"
                                    style="display:none"
                                >
                                    Clear
                                </button>
                            </div>

                            <div class="max-h-64 overflow-y-auto">
                                <template x-if="queueTracks.length === 0">
                                    <div class="px-4 py-8 text-center text-sm text-gray-500 dark:text-white/45">
                                        Queue is empty.
                                    </div>
                                </template>

                                <template x-for="(t, idx) in queueTracks" :key="`${t.id}-${idx}`">
                                    <div
                                        @click="jumpTo(idx)"
                                        class="flex items-center gap-3 px-4 py-3 transition"
                                        :class="idx === queueIndex ? 'bg-gray-900/5 dark:bg-white/10' : 'hover:bg-black/5 dark:hover:bg-white/5'"
                                    >
                                        <img
                                            :src="t.cover || '/images/placeholder-track.svg'"
                                            :alt="t.title"
                                            class="h-11 w-11 rounded-2xl object-cover"
                                        >
                                        <div class="min-w-0 flex-1">
                                            <a
                                                :href="t.url || '#'"
                                                wire:navigate
                                                @click.stop
                                                class="block truncate text-sm font-semibold transition hover:text-primary"
                                                :class="idx === queueIndex ? 'text-gray-900 dark:text-white dark:hover:text-primary-300' : 'text-gray-700 dark:text-white/75 dark:hover:text-primary-300'"
                                                x-text="t.title"
                                            ></a>
                                            <a
                                                :href="t.artist_url || '#'"
                                                wire:navigate
                                                @click.stop
                                                class="block truncate text-xs text-gray-500 transition hover:text-primary dark:text-white/45 dark:hover:text-primary-300"
                                                x-text="t.artist"
                                            ></a>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-[11px] font-semibold uppercase tracking-[0.18em]" :class="idx === queueIndex ? 'text-primary-500 dark:text-primary-300' : 'text-gray-400 dark:text-white/35'" x-text="idx === queueIndex ? 'Live' : `#${idx + 1}`"></span>
                                            <button
                                                type="button"
                                                @click.stop="removeFromQueue(idx)"
                                                class="rounded-full p-2 text-gray-400 transition hover:bg-black/5 hover:text-gray-900 dark:text-white/35 dark:hover:bg-white/10 dark:hover:text-white"
                                                title="Remove"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                </div>
                </div>
            </div>
        </div>

        <div class="sm:hidden">
            <div class="mx-3 mb-3 rounded-[1.75rem] border border-white/20 bg-white/85 px-4 py-3 shadow-2xl backdrop-blur-2xl dark:border-white/10 dark:bg-slate-950/90">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="toggleMobilePlayer(true)"
                        class="flex min-w-0 flex-1 items-center gap-3 text-left"
                    >
                        <img
                            :src="track?.cover || '/images/placeholder-track.svg'"
                            :alt="track?.title"
                            class="h-12 w-12 rounded-2xl object-cover shadow-sm ring-1 ring-white/30"
                        >
                        <div class="min-w-0 flex-1">
                            <a
                                :href="track?.url || '#'"
                                wire:navigate
                                class="block truncate text-sm font-semibold text-gray-900 transition hover:text-primary dark:text-slate-100 dark:hover:text-primary-300"
                                x-text="track?.title"
                            ></a>
                            <a
                                :href="track?.artist_url || '#'"
                                wire:navigate
                                class="mt-0.5 block truncate text-xs text-gray-500 transition hover:text-primary dark:text-slate-400 dark:hover:text-primary-300"
                                x-text="track?.artist"
                            ></a>
                            <div class="mt-2 h-1 overflow-hidden rounded-full bg-gray-200 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-primary-500 transition-all" :style="`width: ${progress}%`"></div>
                            </div>
                        </div>
                    </button>

                    <button
                        type="button"
                        @click="togglePlay()"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-primary-500 text-white shadow-lg transition active:scale-95"
                        :title="playing ? 'Pause' : 'Play'"
                    >
                        <template x-if="!playing">
                            <svg class="ml-0.5 h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                        </template>
                        <template x-if="playing">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M5.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75A.75.75 0 007.25 3h-1.5zM12.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75a.75.75 0 00-.75-.75h-1.5z"/></svg>
                        </template>
                    </button>

                    <button
                        type="button"
                        @click="toggleMobilePlayer(true)"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:border-primary-200 hover:text-primary dark:border-white/10 dark:bg-slate-900 dark:text-slate-300"
                        title="Expand player"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7-7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="hidden sm:block glass-mini-player" :class="{ 'glass-mini-player-playing': playing }">
            <div class="max-w-7xl mx-auto flex items-center gap-4 flex-wrap sm:flex-nowrap">

                {{-- ── Cover + Info ──────────────────────────────────────────────── --}}
                <div class="flex items-center gap-3 min-w-0 w-44 shrink-0">
                    <img
                        :src="track?.cover || '/images/placeholder-track.svg'"
                        :alt="track?.title"
                        class="h-11 w-11 rounded-lg object-cover shadow-sm ring-1 ring-white/30 shrink-0"
                    >
                    <div class="min-w-0">
                        <a
                            :href="track?.url || '#'"
                            wire:navigate
                            class="block truncate text-sm font-semibold text-gray-800 transition hover:text-primary dark:text-slate-200 dark:hover:text-primary-300"
                            x-text="track?.title"
                        ></a>
                        <a
                            :href="track?.artist_url || '#'"
                            wire:navigate
                            class="block truncate text-xs text-gray-500 transition hover:text-primary dark:text-slate-400 dark:hover:text-primary-300"
                            x-text="track?.artist"
                        ></a>
                        <button
                            type="button"
                            x-show="track?.can_gift_artist"
                            @click="openGiftModal()"
                            class="mt-1 inline-flex items-center rounded-full border border-primary-100 bg-primary-50 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-primary-700 transition hover:border-primary-200 hover:bg-primary-100"
                            style="display:none"
                        >
                            Gift artist
                        </button>
                    </div>
                </div>

                {{-- ── Controls ──────────────────────────────────────────────────── --}}
                <div class="flex flex-col items-center gap-1 flex-1">
                    <div class="flex items-center gap-4">
                        <button
                            @click="toggleShuffle()"
                            :aria-pressed="shuffleEnabled"
                            :class="shuffleEnabled ? 'text-primary-600 dark:text-primary-300' : 'text-gray-400 hover:text-gray-700 dark:text-slate-500 dark:hover:text-slate-200'"
                            class="transition"
                            :title="shuffleEnabled ? 'Shuffle on — click for sequential' : 'Sequential — click to shuffle'"
                        >
                            {{-- Sequential: two rows each with a right-pointing chevron --}}
                            <template x-if="!shuffleEnabled">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8h12M3 16h12M13 5l4 3-4 3M13 13l4 3-4 3"/>
                                </svg>
                            </template>
                            {{-- Shuffle: two crossing arrows --}}
                            <template x-if="shuffleEnabled">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6H7L17 16H21M17 12L21 16L17 20M3 18H7L17 8H21M17 4L21 8L17 12"/>
                                </svg>
                            </template>
                        </button>

                        {{-- Prev --}}
                        <button @click="playPrev()"
                                :class="canPlayPrev ? 'text-gray-600 hover:text-gray-900 dark:text-slate-300 dark:hover:text-white' : 'text-gray-300 cursor-default dark:text-slate-600'"
                                class="transition" title="Previous">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M7.75 5.75a.75.75 0 00-1.5 0v12.5a.75.75 0 001.5 0V5.75Zm9.97.297a.75.75 0 00-1.017-.267l-6.25 3.75a2.25 2.25 0 000 3.86l6.25 3.75a.75.75 0 10.771-1.287l-6.25-3.75a.75.75 0 010-1.286l6.25-3.75a.75.75 0 00.246-1.02Z"/></svg>
                        </button>

                        {{-- Play / Pause --}}
                        <button
                            @click="togglePlay"
                            class="w-10 h-10 rounded-full bg-primary-500 text-white shadow-lg flex items-center justify-center hover:bg-primary-600 transition active:scale-95"
                            :title="playing ? 'Pause' : 'Play'"
                        >
                            <template x-if="!playing">
                                <svg class="w-5 h-5 ml-0.5" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                            </template>
                            <template x-if="playing">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M5.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75A.75.75 0 007.25 3h-1.5zM12.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75a.75.75 0 00-.75-.75h-1.5z"/></svg>
                            </template>
                        </button>

                        {{-- Next --}}
                        <button @click="playNext()"
                                :class="canPlayNext ? 'text-gray-600 hover:text-gray-900 dark:text-slate-300 dark:hover:text-white' : 'text-gray-300 cursor-default dark:text-slate-600'"
                                class="transition" title="Next">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M16.25 5.75a.75.75 0 011.5 0v12.5a.75.75 0 01-1.5 0V5.75Zm-9.97.297a.75.75 0 011.017-.267l6.25 3.75a2.25 2.25 0 010 3.86l-6.25 3.75a.75.75 0 11-.771-1.287l6.25-3.75a.75.75 0 000-1.286l-6.25-3.75a.75.75 0 01-.246-1.02Z"/></svg>
                        </button>

                        <button
                            @click="cycleRepeatMode()"
                            :aria-pressed="repeatMode !== 'off'"
                            :class="repeatMode !== 'off' ? 'text-primary-600 dark:text-primary-300' : 'text-gray-400 hover:text-gray-700 dark:text-slate-500 dark:hover:text-slate-200'"
                            class="relative transition"
                            :title="repeatTitle"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 1l4 4-4 4M3 11V9a4 4 0 014-4h14M7 23l-4-4 4-4M21 13v2a4 4 0 01-4 4H3"/>
                            </svg>
                            <span
                                x-show="repeatMode !== 'off'"
                                x-text="repeatMode === 'track' ? '1' : 'ALL'"
                                class="absolute -right-2 -top-2 rounded-full bg-primary-500 px-1.5 py-0.5 text-[8px] font-bold uppercase leading-none text-white"
                                style="display:none"
                            ></span>
                        </button>
                    </div>

                    {{-- Progress bar --}}
                    <div class="flex items-center gap-2 w-full max-w-md">
                        <span class="text-xs tabular-nums text-gray-500 w-8 text-right" x-text="formatTime(currentTime)"></span>
                        <div class="flex-1 h-1.5 bg-gray-200 rounded-full cursor-pointer relative group" @click="seek($event)">
                            <div class="h-full bg-primary-500 rounded-full transition-all" :style="`width: ${progress}%`"></div>
                            <div class="absolute top-1/2 -translate-y-1/2 w-3 h-3 bg-white border-2 border-primary-500 rounded-full shadow opacity-0 group-hover:opacity-100 transition" :style="`left: calc(${progress}% - 6px)`"></div>
                        </div>
                        <span class="text-xs tabular-nums text-gray-500 w-8" x-text="formatTime(duration)"></span>
                    </div>
                </div>

                {{-- ── Like / Share / Download + Volume + Queue toggle ──────────── --}}
                <div class="hidden sm:flex items-center gap-3 shrink-0">

                    {{-- Like --}}
                    <button
                        x-show="hasActiveTrack"
                        @click="$wire.likeToggle()"
                        class="flex items-center gap-1 transition group"
                        :title="$wire.liked ? 'Unlike' : 'Like'"
                        style="display:none"
                    >
                        <svg :class="$wire.liked ? 'w-4 h-4 transition text-primary fill-current' : 'w-4 h-4 transition text-gray-400 group-hover:text-primary'"
                             viewBox="0 0 24 24" :fill="$wire.liked ? 'currentColor' : 'none'" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        <span :class="$wire.liked ? 'text-xs tabular-nums text-primary' : 'text-xs tabular-nums text-gray-400 group-hover:text-primary'"
                              x-text="Number($wire.likesCount) > 0 ? $wire.likesCount : ''"></span>
                    </button>

                    {{-- Share --}}
                    <button
                        @click="shareTrack()"
                        class="relative text-gray-400 hover:text-primary transition"
                        :title="shareCopied ? 'Link copied!' : 'Share track'"
                    >
                        <svg x-show="!shareCopied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                        </svg>
                        <svg x-show="shareCopied" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </button>

                    {{-- Download --}}
                    <button
                        wire:click="downloadTrack"
                        class="text-gray-400 hover:text-primary transition"
                        title="Download"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                    </button>

                    <div class="w-px h-4 bg-gray-200 mx-1"></div>
                    <div class="flex items-center gap-2 w-28">
                        <button @click="toggleMute()" class="text-gray-500 hover:text-gray-800 transition">
                            <template x-if="muted || volume === 0">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.547 3.062A.75.75 0 0110 3.75v12.5a.75.75 0 01-1.264.546L4.703 13H3.167a.75.75 0 01-.7-.48A6.985 6.985 0 012 10c0-.887.165-1.737.468-2.52a.75.75 0 01.7-.48h1.535l4.033-3.796a.75.75 0 01.811-.142zM14.78 7.22a.75.75 0 00-1.06 1.06L14.94 9.5l-1.22 1.22a.75.75 0 001.06 1.06l1.22-1.22 1.22 1.22a.75.75 0 101.06-1.06L17.06 9.5l1.22-1.22a.75.75 0 10-1.06-1.06L16 8.44l-1.22-1.22z"/></svg>
                            </template>
                            <template x-if="!muted && volume > 0">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.547 3.062A.75.75 0 0110 3.75v12.5a.75.75 0 01-1.264.546L4.703 13H3.167a.75.75 0 01-.7-.48A6.985 6.985 0 012 10c0-.887.165-1.737.468-2.52a.75.75 0 01.7-.48h1.535l4.033-3.796a.75.75 0 01.811-.142zM13.28 6.22a.75.75 0 10-1.06 1.06A3.5 3.5 0 0113.5 10a3.5 3.5 0 01-1.28 2.72.75.75 0 101.06 1.06A5 5 0 0015 10a5 5 0 00-1.72-3.78zM16.72 4.22a.75.75 0 00-1.06 1.06A6.5 6.5 0 0117.5 10a6.5 6.5 0 01-1.84 4.72.75.75 0 101.06 1.06A8 8 0 0019 10a8 8 0 00-2.28-5.78z"/></svg>
                            </template>
                        </button>
                        <input
                            type="range" min="0" max="100"
                            x-model="volume"
                            @input="setVolume()"
                            class="w-full accent-primary-500 h-1 cursor-pointer"
                        >
                    </div>

                    {{-- Queue toggle button --}}
                    <button
                        @click="showQueue = !showQueue"
                        :class="showQueue ? 'text-primary-500 bg-primary-50' : 'text-gray-500 hover:text-gray-800'"
                        class="relative p-1.5 rounded-lg transition"
                        title="Queue"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10"/>
                        </svg>
                        <span x-show="queue.length > 1"
                              class="absolute -top-1 -right-1 bg-primary-500 text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center leading-none"
                              x-text="queue.length"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function miniPlayer() {
    const P = window.GrinPlayer; // singleton — survives navigate

    return {
        // ── Reactive state (synced from singleton on init) ───────────────────────
        track:       P.track,
        playing:     false,
        currentTime: Math.floor(P.audio.currentTime) || 0,
        duration:    0,
        volume:      P.volume,
        muted:       P.muted,
        isAuthenticated: @js(auth()->check()),
        hasStripeKey: @js(filled(config('cashier.key'))),
        queue:       P.queue,
        queueTracks: P.queueTracks,
        queueIndex:  P.queueIndex,
        shuffleEnabled: P.shuffleEnabled ?? false,
        repeatMode: P.repeatMode ?? 'off',
        shuffleHistory: Array.isArray(P.shuffleHistory) ? P.shuffleHistory : [],
        shufflePool: Array.isArray(P.shufflePool) ? P.shufflePool : [],
        showQueue:   false,
        mobileExpanded: false,
        isMobileViewport: window.matchMedia('(max-width: 639px)').matches,
        queueToast:  false,
        statusToastMessage: '',
        shareCopied: false,
        giftOpen: false,
        giftPrepared: false,
        giftLoading: false,
        giftProcessing: false,
        giftError: '',
        giftAmount: 3,
        giftMessage: '',
        giftStripe: null,
        giftElements: null,
        giftPaymentElement: null,
        giftPresetOptions: [1, 2, 3, 5, 10],
        _toastTimer: null,
        _statusToastTimer: null,
        _shareTimer: null,
        _mobileMediaQuery: null,
        _syncMobileViewport: null,
        _syncThemeState: null,
        _handlers:   {},

        get hasActiveTrack() {
            return Boolean(this.track?.audio_url);
        },

        get progress() {
            if (!this.duration) return 0;
            return (this.currentTime / this.duration) * 100;
        },

        get canPlayNext() {
            if (!this.track || this.queueIndex < 0 || this.queue.length === 0) {
                return false;
            }

            if (!this.shuffleEnabled) {
                return this.queueIndex < this.queue.length - 1 || this.repeatMode === 'playlist';
            }

            const { pool } = this.getNormalizedShuffleState();

            return pool.length > 0 || this.repeatMode === 'playlist';
        },

        get canPlayPrev() {
            if (this.currentTime > 3) {
                return true;
            }

            if (!this.track || this.queueIndex < 0 || this.queue.length === 0) {
                return false;
            }

            if (!this.shuffleEnabled) {
                return this.queueIndex > 0 || (this.repeatMode === 'playlist' && this.queue.length > 1);
            }

            const { history } = this.getNormalizedShuffleState();

            return history.length > 1;
        },

        get repeatTitle() {
            if (this.repeatMode === 'track') {
                return 'Repeat current track';
            }

            if (this.repeatMode === 'playlist') {
                return 'Repeat playlist';
            }

            return 'Repeat off';
        },

        // ── Lifecycle ──────────────────────────────────────────────────
        init() {
            if (!P.audio.src && !P.audio.currentTime) {
                P.track = null;
                P.queue = [];
                P.queueTracks = [];
                P.queueIndex = -1;
                P.shuffleHistory = [];
                P.shufflePool = [];
            }

            this.track = P.track;
            this.queue = P.queue;
            this.queueTracks = P.queueTracks;
            this.queueIndex = P.queueIndex;
            this.shuffleEnabled = P.shuffleEnabled ?? false;
            this.repeatMode = P.repeatMode ?? 'off';
            this.shuffleHistory = Array.isArray(P.shuffleHistory) ? P.shuffleHistory : [];
            this.shufflePool = Array.isArray(P.shufflePool) ? P.shufflePool : [];
            this.playing  = !P.audio.paused && !!P.audio.src && !P.audio.ended;
            this.duration = Math.floor(P.audio.duration) || 0;
            P.audio.volume = this.volume / 100;
            P.audio.muted  = this.muted;
            this.syncShuffleState();
            if (P.track?.audio_url && !P.audio.src) {
                P.audio.src = P.track.audio_url;
            }
            this._mobileMediaQuery = window.matchMedia('(max-width: 639px)');
            this._syncMobileViewport = (event) => {
                this.isMobileViewport = event.matches;

                if (!this.isMobileViewport) {
                    this.mobileExpanded = false;
                }
            };
            this._syncMobileViewport(this._mobileMediaQuery);
            this._mobileMediaQuery.addEventListener?.('change', this._syncMobileViewport);

            this._syncThemeState = () => {
                this.track = P.track;
            };
            window.addEventListener('grin-theme-changed', this._syncThemeState);

            if (this.hasStripeKey && window.Stripe) {
                this.giftStripe = Stripe('{{ config('cashier.key') }}');
            }

            this._handlers = {
                timeupdate:     () => { this.currentTime = Math.floor(P.audio.currentTime); },
                loadedmetadata: () => { this.duration = Math.floor(P.audio.duration) || 0; },
                play:           () => { this.playing = true; },
                pause:          () => { this.playing = false; },
                ended:          () => {
                    this.playing = false;
                    this.currentTime = 0;
                    const nextIdx = this.getNextQueueIndex({ fromEnded: true });

                    if (nextIdx === null) {
                        return;
                    }

                    if (nextIdx === this.queueIndex) {
                        this.restartCurrentTrack();
                        return;
                    }

                    this.dispatchQueueIndex(nextIdx);
                },
            };
            for (const [evt, fn] of Object.entries(this._handlers)) {
                P.audio.addEventListener(evt, fn);
            }
        },

        destroy() {
            for (const [evt, fn] of Object.entries(this._handlers)) {
                P.audio.removeEventListener(evt, fn);
            }

            this._mobileMediaQuery?.removeEventListener?.('change', this._syncMobileViewport);
            window.removeEventListener('grin-theme-changed', this._syncThemeState);
            this.mobileExpanded = false;
        },

        // ── Track loading ───────────────────────────────────────────────
        onTrackLoaded(detail) {
            const t = detail?.track ?? (Array.isArray(detail) ? detail[0] : detail);
            if (!t || !t.audio_url) {
                console.warn('GrinMusic: track has no audio URL, skipping playback.');
                return;
            }
            const wasEmptyQueue = this.queue.length === 0;
            const existingIdx = this.queue.indexOf(t.id);
            if (existingIdx !== -1) {
                this.queueIndex = existingIdx;
            } else {
                this.queue.push(t.id);
                this.queueTracks.push(t);
                this.queueIndex = this.queue.length - 1;
            }
            if (wasEmptyQueue && Array.isArray(t.auto_queue_tracks) && t.auto_queue_tracks.length) {
                this.appendTracks(t.auto_queue_tracks);
            }
            this.syncShuffleState();
            this.syncPlayerState();
            P.track       = t;
            this.track    = t;

            P.audio.src           = t.audio_url;
            P.audio.volume        = this.volume / 100;
            P.audio.currentTime   = 0;
            P.audio.play()
                .then(() => { this.playing = true; })
                .catch(err => {
                    console.error('Audio play failed:', err.message);
                    this.playing = false;
                });
        },

        // ── Queue operations ──────────────────────────────────────────────
        onQueueAdd(detail) {
            const t = detail?.track ?? (Array.isArray(detail) ? detail[0] : detail);
            if (!t || !t.id) return;
            if (!this.queue.includes(t.id)) {
                this.queue.push(t.id);
                this.queueTracks.push(t);
                this.syncShuffleState();
                this.syncPlayerState();
                this.queueToast = true;
                clearTimeout(this._toastTimer);
                this._toastTimer = setTimeout(() => { this.queueToast = false; }, 2000);
            }
        },

        appendTracks(tracks) {
            if (!Array.isArray(tracks)) return;

            tracks.forEach((candidate) => {
                if (!candidate?.id || this.queue.includes(candidate.id)) {
                    return;
                }

                this.queue.push(candidate.id);
                this.queueTracks.push(candidate);
            });

            this.syncShuffleState();
            this.syncPlayerState();
        },

        jumpTo(idx) {
            if (idx < 0 || idx >= this.queue.length) return;
            if (this.shuffleEnabled) {
                const { history, pool } = this.getNormalizedShuffleState();
                const existingIndex = history.indexOf(idx);

                this.shuffleHistory = existingIndex === -1
                    ? [...history, idx]
                    : history.slice(0, existingIndex + 1);
                this.shufflePool = pool.filter((queueIdx) => queueIdx !== idx);
                P.shuffleHistory = this.shuffleHistory;
                P.shufflePool = this.shufflePool;
            }

            this.dispatchQueueIndex(idx);
        },

        playNext() {
            const nextIdx = this.getNextQueueIndex();

            if (nextIdx === null) {
                return;
            }

            if (nextIdx === this.queueIndex) {
                this.restartCurrentTrack();
                return;
            }

            this.dispatchQueueIndex(nextIdx);
        },

        playPrev() {
            if (this.currentTime > 3) {
                this.resetCurrentTrack();
                return;
            }

            const prevIdx = this.getPrevQueueIndex();

            if (prevIdx === null) {
                this.resetCurrentTrack();
                return;
            }

            this.dispatchQueueIndex(prevIdx);
        },

        removeFromQueue(idx) {
            const nextId = this.queue[idx + 1];
            const wasPlaying = idx === this.queueIndex;
            this.queue.splice(idx, 1);
            this.queueTracks.splice(idx, 1);
            if (wasPlaying) {
                if (nextId) {
                    this.queueIndex = Math.min(idx, this.queue.length - 1);
                    Livewire.dispatch('play-track', { id: nextId });
                } else {
                    this.queueIndex = Math.max(0, idx - 1);
                    P.audio.pause();
                }
            } else if (idx < this.queueIndex) {
                this.queueIndex--;
            }
            this.syncShuffleState();
            this.syncPlayerState();
        },

        clearQueue() {
            P.audio.pause();
            this.playing = false; this.track = null;
            this.queue = []; this.queueTracks = []; this.queueIndex = -1;
            this.shuffleHistory = [];
            this.shufflePool = [];
            P.track = null; P.queue = []; P.queueTracks = []; P.queueIndex = -1; P.shuffleHistory = []; P.shufflePool = [];
            this.mobileExpanded = false;
            this.showQueue = false;
        },

        toggleMobilePlayer(force = null) {
            if (!this.track || !this.isMobileViewport) {
                this.mobileExpanded = false;
                return;
            }

            this.mobileExpanded = typeof force === 'boolean'
                ? force
                : !this.mobileExpanded;

            if (this.mobileExpanded) {
                this.showQueue = false;
            }
        },

        // ── Playback controls ─────────────────────────────────────────────
        getShareUrl() {
            if (!this.track?.url) return null;

            try {
                return new URL(this.track.url, window.location.origin).toString();
            } catch (_) {
                return this.track.url;
            }
        },

        async shareTrack() {
            const shareUrl = this.getShareUrl();
            if (!shareUrl) return;

            const payload = {
                title: this.track?.title || 'Track',
                text: this.track?.artist ? `${this.track.title} by ${this.track.artist}` : (this.track?.title || 'Track'),
                url: shareUrl,
            };

            if (navigator.share) {
                try {
                    await navigator.share(payload);
                    return;
                } catch (error) {
                    if (error?.name === 'AbortError') return;
                }
            }

            let copied = false;

            if (navigator.clipboard?.writeText) {
                try {
                    await navigator.clipboard.writeText(shareUrl);
                    copied = true;
                } catch (_) {
                    copied = false;
                }
            }

            if (!copied) {
                const input = document.createElement('input');
                input.value = shareUrl;
                input.setAttribute('readonly', '');
                input.style.position = 'absolute';
                input.style.left = '-9999px';
                document.body.appendChild(input);
                input.select();
                copied = document.execCommand('copy');
                document.body.removeChild(input);
            }

            if (copied) {
                this.shareCopied = true;
                clearTimeout(this._shareTimer);
                this._shareTimer = setTimeout(() => { this.shareCopied = false; }, 2000);
            }
        },

        showStatusToast(message) {
            if (!message) return;

            this.statusToastMessage = message;
            clearTimeout(this._statusToastTimer);
            this._statusToastTimer = setTimeout(() => {
                this.statusToastMessage = '';
            }, 2500);
        },

        async startBackgroundDownload(detail) {
            if (!detail?.url) {
                return;
            }

            try {
                const url = new URL(detail.url, window.location.origin);
                url.searchParams.set('_dl', Date.now().toString());

                const response = await fetch(url.toString(), {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Download could not be started.');
                }

                const blob = await response.blob();
                const objectUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = objectUrl;
                link.download = detail.filename || 'track.mp3';
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
            } catch (_) {
                this.showStatusToast('Download could not be started.');
            }
        },

        randomGiftAmount() {
            const options = this.giftPresetOptions;

            return options[Math.floor(Math.random() * options.length)] || 3;
        },

        async ensureGiftStripe() {
            if (this.giftStripe || !window.Stripe || !this.hasStripeKey) {
                return;
            }

            this.giftStripe = Stripe('{{ config('cashier.key') }}');
        },

        openGiftModal() {
            if (!this.track?.can_gift_artist) {
                return;
            }

            if (!this.hasStripeKey) {
                this.showStatusToast('Payments are not configured right now.');
                return;
            }

            if (!this.isAuthenticated) {
                Livewire.dispatch('open-modal', {
                    id: 'login-required',
                    title: 'Sign in to gift artist',
                    message: 'Log in or create an account to send support directly to this artist.',
                    loginUrl: '{{ route('login') }}',
                    registerUrl: '{{ route('register', ['role' => 'listener', 'show_role_guide' => 0]) }}',
                });

                return;
            }

            this.giftOpen = true;
            this.giftAmount = this.randomGiftAmount();
            this.giftMessage = '';
            this.giftError = '';
            this.giftProcessing = false;
            this.resetGiftPayment();
        },

        closeGiftModal() {
            this.giftOpen = false;
            this.giftMessage = '';
            this.giftError = '';
            this.giftProcessing = false;
            this.resetGiftPayment();
        },

        selectGiftAmount(amount) {
            this.giftAmount = amount;

            if (this.giftPrepared) {
                this.resetGiftPayment();
            }
        },

        resetGiftPayment() {
            if (this.giftPaymentElement) {
                try {
                    this.giftPaymentElement.unmount();
                } catch (_) {
                }
            }

            this.giftPaymentElement = null;
            this.giftElements = null;
            this.giftPrepared = false;
        },

        async prepareGiftPayment() {
            if (!this.track?.gift_intent_url) {
                this.giftError = 'This artist cannot receive gifts right now.';
                return;
            }

            if (!this.hasStripeKey) {
                this.giftError = 'Payments are not configured right now.';
                return;
            }

            await this.ensureGiftStripe();

            if (!this.giftStripe) {
                this.giftError = 'Payments are unavailable right now.';
                return;
            }

            this.giftError = '';
            this.giftLoading = true;
            this.resetGiftPayment();

            try {
                const response = await fetch(this.track.gift_intent_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        type: 'tip',
                        amount: Number(this.giftAmount),
                        message: this.giftMessage || null,
                    }),
                });

                const data = await response.json();

                if (!response.ok || !data.client_secret) {
                    throw new Error(data.message || 'Could not load payment form.');
                }

                this.giftElements = this.giftStripe.elements({
                    clientSecret: data.client_secret,
                    appearance: { theme: 'night' },
                });
                this.giftPaymentElement = this.giftElements.create('payment');
                this.giftPrepared = true;
                await this.$nextTick();
                this.giftPaymentElement.mount(this.$refs.giftPaymentEl);
            } catch (error) {
                this.giftError = error?.message || 'Could not load payment form.';
            } finally {
                this.giftLoading = false;
            }
        },

        async submitGift() {
            if (!this.giftPrepared || !this.giftStripe || !this.giftElements) {
                return;
            }

            this.giftProcessing = true;
            this.giftError = '';

            const { error } = await this.giftStripe.confirmPayment({
                elements: this.giftElements,
                redirect: 'if_required',
            });

            if (error) {
                this.giftError = error.message || 'Payment could not be completed.';
                this.giftProcessing = false;
                return;
            }

            this.closeGiftModal();
            this.showStatusToast('Gift sent to artist.');
        },

        syncPlayerState() {
            P.queue = this.queue;
            P.queueTracks = this.queueTracks;
            P.queueIndex = this.queueIndex;
            P.shuffleEnabled = this.shuffleEnabled;
            P.repeatMode = this.repeatMode;
            P.shuffleHistory = this.shuffleHistory;
            P.shufflePool = this.shufflePool;
            window.GrinPlayerPersist?.save?.();
        },

        getQueueIndices() {
            return this.queue.map((_, idx) => idx);
        },

        shuffleIndices(indices) {
            const shuffled = [...indices];

            for (let idx = shuffled.length - 1; idx > 0; idx--) {
                const swapIndex = Math.floor(Math.random() * (idx + 1));
                [shuffled[idx], shuffled[swapIndex]] = [shuffled[swapIndex], shuffled[idx]];
            }

            return shuffled;
        },

        getNormalizedShuffleState() {
            const indices = this.getQueueIndices();
            const currentIndex = indices.includes(this.queueIndex) ? this.queueIndex : -1;

            if (!this.shuffleEnabled || !indices.length) {
                return {
                    history: currentIndex >= 0 ? [currentIndex] : [],
                    pool: [],
                };
            }

            let history = Array.isArray(this.shuffleHistory)
                ? this.shuffleHistory.filter((idx, position, values) => indices.includes(idx) && values.indexOf(idx) === position)
                : [];

            if (currentIndex >= 0) {
                const currentPosition = history.lastIndexOf(currentIndex);

                history = currentPosition === -1
                    ? [...history, currentIndex]
                    : history.slice(0, currentPosition + 1);
            } else {
                history = [];
            }

            let pool = Array.isArray(this.shufflePool)
                ? this.shufflePool.filter((idx, position, values) => indices.includes(idx) && !history.includes(idx) && values.indexOf(idx) === position)
                : [];

            const missing = indices.filter((idx) => idx !== currentIndex && !history.includes(idx) && !pool.includes(idx));

            if (missing.length) {
                pool = [...pool, ...this.shuffleIndices(missing)];
            }

            return { history, pool };
        },

        syncShuffleState() {
            const { history, pool } = this.getNormalizedShuffleState();

            this.shuffleHistory = history;
            this.shufflePool = pool;
            P.shuffleHistory = history;
            P.shufflePool = pool;
        },

        dispatchQueueIndex(idx) {
            if (idx < 0 || idx >= this.queue.length) {
                return;
            }

            Livewire.dispatch('play-track', { id: this.queue[idx] });
        },

        getNextQueueIndex({ fromEnded = false } = {}) {
            if (this.queueIndex < 0 || this.queue.length === 0) {
                return null;
            }

            if (fromEnded && this.repeatMode === 'track') {
                return this.queueIndex;
            }

            if (!this.shuffleEnabled) {
                const nextIdx = this.queueIndex + 1;

                if (nextIdx < this.queue.length) {
                    return nextIdx;
                }

                if (this.repeatMode === 'playlist') {
                    return this.queue.length === 1 ? this.queueIndex : 0;
                }

                return null;
            }

            const { history, pool } = this.getNormalizedShuffleState();

            if (pool.length > 0) {
                const [nextIdx, ...remainingPool] = pool;

                this.shuffleHistory = [...history, nextIdx];
                this.shufflePool = remainingPool;
                P.shuffleHistory = this.shuffleHistory;
                P.shufflePool = this.shufflePool;

                return nextIdx;
            }

            if (this.repeatMode !== 'playlist') {
                return null;
            }

            const remaining = this.getQueueIndices().filter((idx) => idx !== this.queueIndex);

            if (!remaining.length) {
                return this.queueIndex;
            }

            const [nextIdx, ...remainingPool] = this.shuffleIndices(remaining);

            this.shuffleHistory = [this.queueIndex, nextIdx];
            this.shufflePool = remainingPool;
            P.shuffleHistory = this.shuffleHistory;
            P.shufflePool = this.shufflePool;

            return nextIdx;
        },

        getPrevQueueIndex() {
            if (this.queueIndex < 0 || this.queue.length === 0) {
                return null;
            }

            if (!this.shuffleEnabled) {
                const prevIdx = this.queueIndex - 1;

                if (prevIdx >= 0) {
                    return prevIdx;
                }

                if (this.repeatMode === 'playlist' && this.queue.length > 1) {
                    return this.queue.length - 1;
                }

                return null;
            }

            const { history, pool } = this.getNormalizedShuffleState();

            if (history.length <= 1) {
                return null;
            }

            const previousIndex = history[history.length - 2];
            const currentIndex = history[history.length - 1];

            this.shuffleHistory = history.slice(0, -1);
            this.shufflePool = [currentIndex, ...pool.filter((idx) => idx !== currentIndex)];
            P.shuffleHistory = this.shuffleHistory;
            P.shufflePool = this.shufflePool;

            return previousIndex;
        },

        resetCurrentTrack() {
            P.audio.currentTime = 0;
            this.currentTime = 0;
        },

        restartCurrentTrack() {
            this.resetCurrentTrack();
            P.audio.play()
                .then(() => { this.playing = true; })
                .catch(err => { console.error('Play failed:', err.message); });
        },

        toggleShuffle() {
            this.shuffleEnabled = !this.shuffleEnabled;

            if (this.shuffleEnabled) {
                const remaining = this.getQueueIndices().filter((idx) => idx !== this.queueIndex);
                this.shuffleHistory = this.queueIndex >= 0 ? [this.queueIndex] : [];
                this.shufflePool = this.shuffleIndices(remaining);
                this.showStatusToast('Shuffle on.');
            } else {
                this.shuffleHistory = this.queueIndex >= 0 ? [this.queueIndex] : [];
                this.shufflePool = [];
                this.showStatusToast('Sequential play.');
            }

            this.syncPlayerState();
        },

        cycleRepeatMode() {
            this.repeatMode = this.repeatMode === 'off'
                ? 'track'
                : this.repeatMode === 'track'
                    ? 'playlist'
                    : 'off';

            this.syncPlayerState();

            if (this.repeatMode === 'track') {
                this.showStatusToast('Repeat current track.');
                return;
            }

            if (this.repeatMode === 'playlist') {
                this.showStatusToast('Repeat playlist.');
                return;
            }

            this.showStatusToast('Repeat off.');
        },

        togglePlay() {
            if (!this.track?.audio_url) return;
            if (!P.audio.src) {
                P.audio.src    = this.track.audio_url;
                P.audio.volume = this.volume / 100;
                P.audio.muted  = this.muted;
            }
            if (this.playing) {
                P.audio.pause();
            } else {
                P.audio.play()
                    .then(() => { this.playing = true; })
                    .catch(err => { console.error('Play failed:', err.message); });
            }
        },

        toggleMute() {
            this.muted = !this.muted;
            P.audio.muted = this.muted; P.muted = this.muted;
        },

        setVolume() {
            P.audio.volume = this.volume / 100; P.volume = this.volume;
            if (this.volume > 0) { this.muted = false; P.audio.muted = false; P.muted = false; }
        },

        seek(ev) {
            const rect  = ev.currentTarget.getBoundingClientRect();
            const ratio = (ev.clientX - rect.left) / rect.width;
            P.audio.currentTime = ratio * this.duration;
        },

        formatTime(seconds) {
            if (!seconds || isNaN(seconds)) return '0:00';
            const m = Math.floor(seconds / 60);
            const s = Math.floor(seconds % 60);
            return `${m}:${s.toString().padStart(2, '0')}`;
        },
    };
}
</script>

@once
    <script src="https://js.stripe.com/v3/" defer></script>
@endonce
