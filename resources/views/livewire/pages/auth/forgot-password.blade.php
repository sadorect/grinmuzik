<?php

use App\Rules\Captcha;
use App\Rules\Honeypot;
use App\Support\AuthThrottle;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email    = '';
    public string $captcha  = '';
    public string $website  = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        // Volt forms submit to /livewire/update, so the route's throttle
        // middleware never sees this call — the limit has to run here.
        AuthThrottle::ensureAllowed('password_reset_request', 'email');

        $this->validate([
            'email'   => ['required', 'string', 'email'],
            'captcha' => ['required', new Captcha('password-reset-request')],
            'website' => [new Honeypot],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <div class="mb-6 text-center">
        <h1 class="text-2xl font-bold text-gray-800">Reset Password</h1>
        <p class="text-sm text-gray-500 mt-1">We'll email you a link to reset your password.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="space-y-4">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus placeholder="you@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-captcha-field model="captcha" honeypot="website" form-key="password-reset-request" />

        <x-primary-button class="w-full justify-center">
            {{ __('Send Reset Link') }}
        </x-primary-button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        <a href="{{ route('login') }}" wire:navigate class="text-red-500 hover:underline">← Back to login</a>
    </p>
</div>
