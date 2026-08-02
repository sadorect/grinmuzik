{{--
    Turnstile widget for the Filament admin login.

    Kept separate from <x-captcha-field> because Filament fields carry their own
    statePath plumbing: the token is written with $wire.set($statePath, …) rather
    than to a bare property, and there is no honeypot here (the admin login is
    not a form bots discover by crawling).
--}}
@php($captcha = app(\App\Services\CaptchaService::class))

<div
    wire:ignore
    x-data="{
        widgetId: null,

        init() {
            this.renderWhenReady();

            // Every Livewire round-trip spends the token we are holding, so
            // queue a fresh challenge as soon as the response lands — otherwise
            // a second attempt after a wrong password is rejected for a stale
            // token rather than for the real reason.
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
                sitekey: @js($captcha->siteKey()),
                theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                action: 'admin-login',
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
            this.$wire.set(@js($getStatePath()), token, false);
        },
    }"
>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>

    <div x-ref="widget"></div>

    <noscript>
        <p class="text-sm text-danger-600">
            {{ __('JavaScript is required to sign in.') }}
        </p>
    </noscript>
</div>
