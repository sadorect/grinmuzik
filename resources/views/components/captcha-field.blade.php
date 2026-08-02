{{--
    Anti-abuse field for the public forms.

    Renders Cloudflare Turnstile when it is configured, and falls back to the
    legacy math challenge otherwise. Callers do not need to know which is
    active — both write to the same Livewire property, and both are checked by
    the App\Rules\Captcha rule.

    Also emits a honeypot input, so every form gets two independent layers on
    top of the rate limiting in App\Support\AuthThrottle.

    Usage:  <x-captcha-field model="captcha" honeypot="website" form-key="login" />

    NOTE: these are Livewire forms, not classic POSTs. A Turnstile token is
    single-use, so the widget is reset after every server round-trip — without
    that, a second attempt after a failed login is rejected for a stale token
    rather than for the real reason.
--}}
@props([
    'model' => 'captcha',
    'honeypot' => 'website',
    'formKey' => 'default',
])

@php($captcha = app(\App\Services\CaptchaService::class))

{{-- Hidden from people, irresistible to naive bots. Any value fails
     validation. Kept out of the tab order and off-screen rather than
     display:none, which some bots specifically skip. --}}
<div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
    <label for="{{ $honeypot }}">Website</label>
    <input
        type="text"
        id="{{ $honeypot }}"
        wire:model="{{ $honeypot }}"
        tabindex="-1"
        autocomplete="off"
    >
</div>

@if ($captcha->enabled())
    @if ($captcha->usesTurnstile())

        @once
            @push('scripts')
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
                <script>
                    document.addEventListener('alpine:init', () => {
                        Alpine.data('turnstileField', (config) => ({
                            widgetId: null,

                            init() {
                                this.renderWhenReady();

                                // The site's dark mode is class-based and toggled
                                // by hand, so Turnstile's own 'auto' (which follows
                                // the OS) would show a light widget on a dark card.
                                // Re-render on theme change to match.
                                window.addEventListener('grin-theme-changed', () => {
                                    if (this.widgetId !== null && window.turnstile) {
                                        window.turnstile.remove(this.widgetId);
                                        this.widgetId = null;
                                    }

                                    this.renderWhenReady();
                                });

                                // Every Livewire round-trip spends the token we
                                // are holding, so queue a fresh challenge as soon
                                // as the response lands.
                                Livewire.hook('commit', ({ component, respond }) => {
                                    if (component.id !== this.$wire.id) {
                                        return;
                                    }

                                    respond(() => queueMicrotask(() => this.reset()));
                                });
                            },

                            renderWhenReady() {
                                if (! window.turnstile) {
                                    setTimeout(() => this.renderWhenReady(), 100);

                                    return;
                                }

                                this.widgetId = window.turnstile.render(this.$refs.widget, {
                                    sitekey: config.siteKey,
                                    theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                                    action: config.action,
                                    'refresh-expired': 'auto',
                                    callback: (token) => this.store(token),
                                    'expired-callback': () => this.store(''),
                                    'error-callback': () => this.store(''),
                                });
                            },

                            reset() {
                                if (this.widgetId !== null && window.turnstile) {
                                    window.turnstile.reset(this.widgetId);
                                }

                                this.store('');
                            },

                            store(token) {
                                // Deferred: the token rides along with the next
                                // submit rather than costing a round-trip of its
                                // own the moment the challenge solves.
                                this.$wire.set(config.model, token, false);
                            },
                        }));
                    });
                </script>
            @endpush
        @endonce

        <div
            class="mt-4"
            wire:ignore
            x-data="turnstileField({
                siteKey: @js($captcha->siteKey()),
                model: @js($model),
                action: @js(Str::slug($formKey)),
            })"
        >
            <div x-ref="widget"></div>
        </div>

        <noscript>
            <p class="mt-2 text-sm text-red-600">
                {{ __('JavaScript is required to complete the security check on this form.') }}
            </p>
        </noscript>

        <x-input-error :messages="$errors->get($model)" class="mt-2" />

    @else

        <div class="mt-4">
            <x-input-label
                for="{{ $model }}"
                :value="__('Security check: what is :question ?', ['question' => $captcha->generateMathChallenge($formKey)])"
            />
            <x-text-input
                id="{{ $model }}"
                type="number"
                class="block mt-1 w-full"
                wire:model="{{ $model }}"
                autocomplete="off"
                inputmode="numeric"
                placeholder="{{ __('Your answer') }}"
                required
            />
            <x-input-error :messages="$errors->get($model)" class="mt-2" />
        </div>

    @endif
@endif
