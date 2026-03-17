<?php

use App\StagesLivewireUploads;
use App\Support\UploadLimits;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use StagesLivewireUploads;
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public bool $removeExistingAvatar = false;
    public $avatar = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updatedAvatar(): void
    {
        $this->removeExistingAvatar = false;

        $this->validateOnly('avatar', [
            'avatar' => ['nullable', 'image', 'max:' . UploadLimits::imageKb()],
        ]);
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'max:' . UploadLimits::imageKb()],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($this->removeExistingAvatar) {
            $user->clearMediaCollection('avatar');
        }

        if ($this->avatar) {
            $this->addStagedMedia(
                $user,
                $this->avatar,
                'avatar',
                'avatar.' . strtolower($this->avatar->getClientOriginalExtension()),
                'avatar'
            );
        }

        $this->reset('avatar');
        $this->removeExistingAvatar = false;

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
        <div>
            <x-input-label for="avatar" :value="__('Account Photo')" />

            <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="h-20 w-20 overflow-hidden rounded-2xl bg-gradient-to-br from-primary-100 to-primary-200 ring-2 ring-white shadow-sm">
                    @if($avatar)
                        <img src="{{ $avatar->temporaryUrl() }}" alt="Avatar preview" class="h-full w-full object-cover">
                    @elseif(!$removeExistingAvatar && auth()->user()->getAvatarMediaUrl())
                        <img src="{{ auth()->user()->getAvatarMediaUrl() }}" alt="{{ auth()->user()->name }}" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center text-2xl font-black text-primary-400">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                    @endif
                </div>

                <div class="flex-1 space-y-3">
                    <label class="block rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-3 text-sm text-gray-600 transition hover:border-primary-300 hover:bg-primary-50/40">
                        <span class="block font-semibold text-gray-900">Upload new account photo</span>
                        <span class="mt-1 block text-xs text-gray-500">JPG, PNG, or WebP up to {{ UploadLimits::formatKilobytes(UploadLimits::imageKb()) }}</span>
                        <input wire:model="avatar" id="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only">
                    </label>

                    @if(!$avatar && auth()->user()->getFirstMediaUrl('avatar'))
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" wire:model.live="removeExistingAvatar" class="rounded border-gray-300 text-primary focus:ring-primary">
                            Remove current account photo
                        </label>
                    @endif
                </div>
            </div>

            <x-input-error class="mt-2" :messages="$errors->get('avatar')" />
        </div>

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1 block w-full" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button wire:click.prevent="sendVerification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            <x-action-message class="me-3" on="profile-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
