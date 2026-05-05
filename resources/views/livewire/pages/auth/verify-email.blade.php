<?php

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public function mount(): void
    {
        if (! SiteSetting::registrationRequiresEmailVerification()) {
            Auth::user()?->refresh()->syncArtistVerificationState();
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
        }
    }

    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
            return;
        }

        session()->flash('status', $this->sendVerificationLink(Auth::user()));
    }

    public function logout(): void
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        $this->redirect(route('home'), navigate: true);
    }

    protected function sendVerificationLink($user): string
    {
        try {
            $user->sendEmailVerificationNotification();

            return 'verification-link-sent';
        } catch (\Throwable $exception) {
            report($exception);

            return 'verification-link-failed';
        }
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

    @if (session('status') === 'verification-link-failed')
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ __("Your account was created, but we couldn't send the verification email right now. Please try the resend button again shortly, or contact support if the issue continues.") }}
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
