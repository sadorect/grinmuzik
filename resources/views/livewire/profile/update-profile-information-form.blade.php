<?php

use App\StagesLivewireUploads;
use App\Support\UploadLimits;
use App\Support\ArtistActivityNotifier;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use StagesLivewireUploads;
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public bool $removeExistingAvatar = false;
    public $avatar = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->phone = (string) (Auth::user()->phone ?? '');
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
            'phone' => ['nullable', 'string', 'max:32'],
            'avatar' => ['nullable', 'image', 'max:' . UploadLimits::imageKb()],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => blank($validated['phone'] ?? null) ? null : trim((string) $validated['phone']),
        ]);

        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = \App\Models\SiteSetting::registrationRequiresEmailVerification()
                ? null
                : now();
        }

        try {
            $user->save();

            if ($emailChanged && \App\Models\SiteSetting::registrationRequiresEmailVerification()) {
                $user->sendEmailVerificationNotification();
            }

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

            app(ArtistActivityNotifier::class)->sendAccountUpdated($user, $emailChanged);

            $this->dispatch('profile-updated', name: $user->name);
            $this->dispatch('profile-feedback', type: 'success', message: 'Account details updated successfully.');
        } catch (\Throwable $exception) {
            report($exception);

            $this->addError('profile', "We couldn't save your account changes right now. Please try again.");
            $this->dispatch('profile-feedback', type: 'error', message: 'We could not save your changes. Please try again.');
        }
    }
}; ?>

<section
    x-data="{ visible: false, type: 'success', message: '', timer: null }"
    x-on:profile-feedback.window="
        type = $event.detail.type || 'success';
        message = $event.detail.message || '';
        visible = !!message;
        clearTimeout(timer);
        timer = setTimeout(() => visible = false, 3200);
    "
>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <div
        x-show="visible"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="mt-4 rounded-2xl border px-4 py-3 text-sm shadow-sm"
        :class="type === 'error'
            ? 'border-rose-200 bg-rose-50 text-rose-800'
            : 'border-emerald-200 bg-emerald-50 text-emerald-800'"
        style="display:none"
    >
        <div class="flex items-start gap-3">
            <svg x-show="type !== 'error'" class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <svg x-show="type === 'error'" class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z" />
            </svg>
            <p class="leading-6" x-text="message"></p>
        </div>
    </div>

    @if ($errors->has('profile'))
        <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm">
            {{ $errors->first('profile') }}
        </div>
    @endif

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
        </div>

        <div>
            <x-input-label for="phone" :value="__('Phone Number')" />
            <x-text-input wire:model="phone" id="phone" name="phone" type="tel" class="mt-1 block w-full" autocomplete="tel" />
            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>
        </div>
    </form>
</section>
