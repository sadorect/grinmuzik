{{--
    The report trigger and its modal.

    Uses the shared <x-modal> rather than a bespoke overlay, so focus trapping,
    escape-to-close, scroll locking and the transitions all behave the way every
    other modal in the app does.

    x-modal is driven by window events, so opening is a two-step: the Livewire
    action resets the form state, then dispatches open-modal to Alpine. $modalName
    is per-instance because a page can carry several of these (a track plus each
    of its comments).
--}}
<div>
    <button
        type="button"
        wire:click="openModal"
        x-on:click="$dispatch('open-modal', '{{ $this->modalName }}')"
        class="inline-flex items-center gap-1.5 text-sm transition {{ $this->triggerClasses() }}"
    >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18M3 4h13l-2 4 2 4H3" />
        </svg>
        {{ __('Report') }}
    </button>

    <x-modal :name="$this->modalName" max-width="md" focusable>
        <div class="p-6">
            @if ($submitted)
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('Thanks — we\'ve got it') }}
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Our team reviews every report. We won\'t share your details with the person who posted this.') }}
                </p>

                <button
                    type="button"
                    x-on:click="$dispatch('close-modal', '{{ $this->modalName }}')"
                    class="mt-6 w-full rounded-lg bg-gray-900 px-4 py-2.5 font-medium text-white transition hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600"
                >
                    {{ __('Close') }}
                </button>
            @else
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('Report this content') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Tell us what\'s wrong and our team will review it.') }}
                </p>

                <form wire:submit="submit" class="mt-4 space-y-4">
                    <div>
                        <x-input-label :for="$this->modalName.'-reason'" :value="__('Reason')" />
                        <select
                            id="{{ $this->modalName }}-reason"
                            wire:model="reason"
                            class="w-full rounded-xl border border-gray-200 bg-white/60 px-4 py-2.5 text-sm text-gray-800 transition focus:border-transparent focus:outline-none focus:ring-2 focus:ring-primary-400 dark:border-gray-600 dark:bg-gray-900/60 dark:text-gray-100"
                            required
                        >
                            <option value="">{{ __('Choose a reason') }}</option>
                            @foreach ($reasons as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label :for="$this->modalName.'-details'" :value="__('Anything else? (optional)')" />
                        <textarea
                            id="{{ $this->modalName }}-details"
                            wire:model="details"
                            rows="3"
                            maxlength="1000"
                            class="w-full rounded-xl border border-gray-200 bg-white/60 px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 transition focus:border-transparent focus:outline-none focus:ring-2 focus:ring-primary-400 dark:border-gray-600 dark:bg-gray-900/60 dark:text-gray-100 dark:placeholder-gray-500"
                            placeholder="{{ __('Add any detail that would help us review this.') }}"
                        ></textarea>
                        <x-input-error :messages="$errors->get('details')" class="mt-2" />
                    </div>

                    @guest
                        <div>
                            <x-input-label :for="$this->modalName.'-email'" :value="__('Your email (optional)')" />
                            <x-text-input
                                id="{{ $this->modalName }}-email"
                                wire:model="reporterEmail"
                                type="email"
                                placeholder="you@example.com"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ __('Only used if we need to ask you about this report.') }}
                            </p>
                            <x-input-error :messages="$errors->get('reporterEmail')" class="mt-2" />
                        </div>
                    @endguest

                    <x-captcha-field model="captcha" honeypot="website" :form-key="'content-report'" />

                    <div class="flex gap-3 pt-2">
                        <button
                            type="submit"
                            class="flex-1 rounded-lg bg-red-600 px-4 py-2.5 font-medium text-white transition hover:bg-red-700"
                        >
                            <span wire:loading.remove wire:target="submit">{{ __('Submit report') }}</span>
                            <span wire:loading wire:target="submit">{{ __('Sending...') }}</span>
                        </button>

                        <button
                            type="button"
                            x-on:click="$dispatch('close-modal', '{{ $this->modalName }}')"
                            class="rounded-lg border border-gray-300 px-4 py-2.5 font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            {{ __('Cancel') }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </x-modal>
</div>
