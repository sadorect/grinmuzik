<?php

use App\Livewire\Forms\LoginForm;
use App\Rules\Captcha;
use App\Rules\Honeypot;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;
    public string $captcha = '';
    public string $website = '';

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate([
            'captcha' => ['required', new Captcha('login')],
            'website' => [new Honeypot],
        ]);

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6 text-center">
        <h1 class="text-2xl font-bold text-gray-800">Welcome back</h1>
        <p class="mt-1 text-sm text-gray-500">Sign in to your {{ $siteName }} account</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-4">
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="mt-1 block w-full" type="email" name="email" required autofocus autocomplete="username" placeholder="you@example.com" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <div x-data="{ showPassword: false }">
            <x-input-label for="password" :value="__('Password')" />
            <div class="relative mt-1">
                <x-text-input wire:model="form.password" id="password" class="block w-full pr-12"
                    x-bind:type="showPassword ? 'text' : 'password'"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="........" />
                <button type="button"
                    x-on:click="showPassword = !showPassword"
                    x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 transition hover:text-gray-600 focus:outline-none focus:text-gray-600">
                    <svg x-show="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        <circle cx="12" cy="12" r="3" stroke-width="1.75" />
                    </svg>
                    <svg x-show="showPassword" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 3l18 18" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10.585 10.587A2 2 0 0012 14a2 2 0 001.414-.586" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9.88 5.09A10.94 10.94 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.96 10.96 0 01-4.132 5.411M6.228 6.228A10.957 10.957 0 002.458 12c1.274 4.057 5.065 7 9.542 7 1.566 0 3.055-.36 4.385-1.004" />
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember" class="flex cursor-pointer items-center gap-2">
                <input wire:model="form.remember" id="remember" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-red-500 focus:ring-red-400" name="remember">
                <span class="text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
            @if (Route::has('password.request'))
                <a class="text-sm text-red-500 hover:underline" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot password?') }}
                </a>
            @endif
        </div>

        <x-captcha-field model="captcha" honeypot="website" form-key="login" />

        <x-primary-button class="mt-2 w-full justify-center">
            <span wire:loading.remove wire:target="login">{{ __('Sign In') }}</span>
            <span wire:loading wire:target="login">Signing in...</span>
        </x-primary-button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        Don't have an account?
        <a href="{{ route('register') }}" wire:navigate class="font-medium text-red-500 hover:underline">Sign up free</a>
    </p>
</div>
