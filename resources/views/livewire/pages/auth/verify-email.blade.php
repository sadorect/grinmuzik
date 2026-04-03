<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        session()->flash('status', 'verification-link-sent');
    }

    public function logout(): void
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        $this->redirect(route('home'), navigate: true);
    }
}; ?>

<div>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Thanks for signing up! Before getting started, please verify your email address by clicking the link we sent to your email. If you didn\'t receive it, we\'ll send you another.') }}
    </div>

    @if (session('status') === 'verification-link-sent')
        <div class="mb-4 text-sm font-medium text-green-600">
            {{ __('A new verification link has been sent to your email address.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <x-primary-button wire:click="sendVerification">
            {{ __('Resend Verification Email') }}
        </x-primary-button>

        <button wire:click="logout"
                class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            {{ __('Log Out') }}
        </button>
    </div>
</div>
