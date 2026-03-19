@php($installAppName = 'GrinMuzik')
@php($installPromptLogo = $siteSettings?->site_logo_url ?? route('pwa.icon'))

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
            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-primary-100 bg-white shadow-sm">
                <img src="{{ $installPromptLogo }}" alt="{{ $installAppName }} logo" class="h-full w-full object-contain p-2" />
            </div>

            <div>
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-primary-500">Install App</p>
                <h2 class="text-xl font-bold text-gray-900">Install {{ $installAppName }}</h2>
                <div class="mt-2 text-sm text-gray-500">
                    <template x-if="installAvailable">
                        <p>Install {{ $installAppName }} on this device.</p>
                    </template>

                    <template x-if="!installAvailable && showIosInstructions">
                        <p>In Safari, tap Share, then Add to Home Screen.</p>
                    </template>
                </div>
            </div>
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
                Close
            </button>
        </div>
    </div>
</div>
