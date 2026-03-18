<div
    x-data="pwaInstallPrompt()"
    x-init="init()"
    x-show="open"
    x-transition.opacity
    class="fixed inset-0 z-[10000] flex items-end justify-center p-4 sm:items-center"
    style="display:none"
>
    <div class="absolute inset-0 bg-gray-950/45 backdrop-blur-sm" @click="dismiss()"></div>

    <div class="relative w-full max-w-md overflow-hidden rounded-[2rem] border border-white/60 bg-white/95 p-6 shadow-2xl">
        <button
            type="button"
            @click="dismiss()"
            class="absolute right-4 top-4 rounded-full border border-gray-200 bg-white/80 p-2 text-gray-400 transition hover:text-gray-700"
            aria-label="Close install prompt"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <div class="mb-5 flex items-start gap-4 pr-10">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-primary-100 text-primary">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 16V4m0 0l-4 4m4-4l4 4M5 14v4a2 2 0 002 2h10a2 2 0 002-2v-4" />
                </svg>
            </div>

            <div>
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-primary-500">Install App</p>
                <h2 class="text-xl font-bold text-gray-900">Install {{ $siteName }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-gray-500">
                    Add {{ $siteName }} to your desktop or home screen so it opens like an app without needing a browser tab each time.
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-primary-100 bg-primary-50/70 p-4 text-sm text-gray-600">
            <template x-if="installAvailable">
                <p>
                    You can install {{ $siteName }} right now for a cleaner, faster launch experience with its own app icon.
                </p>
            </template>

            <template x-if="!installAvailable && showIosInstructions">
                <div class="space-y-2">
                    <p>On iPhone or iPad, tap the Share button in Safari and choose <span class="font-semibold text-gray-800">Add to Home Screen</span>.</p>
                    <p>Once added, {{ $siteName }} will launch from your home screen like an app.</p>
                </div>
            </template>
        </div>

        <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button
                type="button"
                @click="dismiss()"
                class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-600 transition hover:border-gray-300 hover:text-gray-900"
            >
                Not now
            </button>

            <button
                type="button"
                x-show="installAvailable"
                @click="install()"
                class="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-600"
                style="display:none"
            >
                Install App
            </button>

            <button
                type="button"
                x-show="!installAvailable && showIosInstructions"
                @click="dismiss()"
                class="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-600"
                style="display:none"
            >
                Okay
            </button>
        </div>
    </div>
</div>
