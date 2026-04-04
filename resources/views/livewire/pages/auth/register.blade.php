<?php

use App\Models\ArtistProfile;
use App\Models\Genre;
use App\Models\User;
use App\Rules\CaptchaAnswer;
use App\StagesLivewireUploads;
use App\Support\UploadLimits;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.guest')] class extends Component
{
    use StagesLivewireUploads;
    use WithFileUploads;

    public string $name        = '';
    public string $email       = '';
    public string $password    = '';
    public string $password_confirmation = '';
    public string $role        = 'artist'; // 'listener' | 'artist'
    public string $captcha     = '';
    public bool $hasAcceptedPolicies = false;

    // Artist-only fields
    public string $stage_name = '';
    public string $bio        = '';
    public array  $selectedGenres = [];
    public $avatar = null;

    public function mount(): void
    {
        $this->genres = Genre::active()->orderBy('name')->get()->toArray();
    }

    public array $genres = [];

    protected function rules(): array
    {
        $rules = [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'role'     => ['required', 'in:listener,artist'],
            'captcha'  => ['required', 'integer', new CaptchaAnswer],
            'hasAcceptedPolicies' => ['accepted'],
        ];

        if ($this->role === 'artist') {
            $rules['stage_name']     = ['required', 'string', 'max:100'];
            $rules['bio']            = ['nullable', 'string', 'max:500'];
            $rules['selectedGenres'] = ['nullable', 'array', 'max:5'];
            $rules['avatar']         = ['nullable', 'image', 'max:' . UploadLimits::imageKb()];
        }

        return $rules;
    }

    public function updatedAvatar(): void
    {
        if ($this->role !== 'artist') {
            return;
        }

        $this->validateOnly('avatar');
    }

    public function register()
    {
        $this->resetErrorBag('register');
        $this->validate();

        $user = null;

        try {
            DB::transaction(function () use (&$user): void {
                $user = User::create([
                    'name'     => $this->name,
                    'email'    => $this->email,
                    'password' => Hash::make($this->password),
                ]);

                Role::findOrCreate($this->role, 'web');
                $user->assignRole($this->role);

                if ($this->role === 'artist') {
                    $profile = new ArtistProfile([
                        'user_id'    => $user->id,
                        'stage_name' => $this->stage_name,
                        'bio'        => $this->bio,
                        'is_verified' => $user->hasVerifiedEmail(),
                        'is_approved' => $user->hasVerifiedEmail(),
                        'is_active' => true,
                    ]);
                    $profile->save();

                    if (!empty($this->selectedGenres)) {
                        $profile->genres()->sync($this->selectedGenres);
                    }

                    if ($this->avatar) {
                        $this->addStagedMedia(
                            $profile,
                            $this->avatar,
                            'avatar',
                            'avatar.' . strtolower($this->avatar->getClientOriginalExtension()),
                            'avatar'
                        );
                    }
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            $this->addError('register', "We couldn't finish creating your account right now. Please review the form and try again.");

            return;
        }

        Auth::login($user);
        Session::regenerate();

        session()->flash('status', $this->sendVerificationLink($user));

        return $this->redirectRoute('verification.notice', navigate: true);
    }

    protected function sendVerificationLink(User $user): string
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

<div x-data="{ showRoleGuide: @js($showRegistrationRoleGuide ?? true) }" x-on:keydown.escape.window="showRoleGuide = false">
    @if($showRegistrationRoleGuide ?? true)
    <div
        x-show="showRoleGuide"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
        style="display:none"
        aria-labelledby="register-role-guide-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="absolute inset-0 bg-slate-950/45 backdrop-blur-sm" x-on:click="showRoleGuide = false"></div>

        <div
            x-show="showRoleGuide"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative w-full max-w-xl overflow-hidden rounded-[2rem] border border-white/70 bg-white/95 shadow-2xl"
            x-on:click.stop
        >
            <div class="absolute inset-x-0 top-0 h-44 bg-gradient-to-r from-primary-500 via-primary-400 to-secondary-400 opacity-95"></div>
            <div class="absolute -left-12 top-8 h-32 w-32 rounded-full bg-white/15 blur-3xl"></div>
            <div class="absolute -right-10 top-4 h-28 w-28 rounded-full bg-white/20 blur-3xl"></div>

            <button
                type="button"
                class="absolute right-4 top-4 z-20 inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/60 bg-white/85 text-gray-600 shadow-sm transition hover:bg-white hover:text-gray-900"
                x-on:click.stop="showRoleGuide = false"
                aria-label="Close quick guide"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <div class="relative z-10 px-6 pt-8 sm:px-8 sm:pt-10">
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.28em] text-white/80">Quick Guide</p>
                <h2 id="register-role-guide-title" class="max-w-lg text-2xl font-bold text-white sm:text-[2rem]">
                    Choose the tab that matches your reason for joining
                </h2>
            </div>

            <div class="relative z-10 px-6 pb-6 pt-6 sm:px-8 sm:pb-8">
                <div class="rounded-[1.7rem] border border-white/80 bg-white/92 p-5 shadow-lg shadow-slate-900/5 backdrop-blur">
                    <p class="text-sm leading-6 text-gray-600">
                        Artists should stay on <span class="font-semibold text-gray-900">Release my music</span>, while listeners can switch to
                        <span class="font-semibold text-gray-900">Listen to music</span> to keep the form short and simple.
                    </p>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <button
                        type="button"
                        x-on:click="$wire.set('role', 'artist'); showRoleGuide = false"
                        class="group rounded-[1.6rem] border border-primary-100 bg-gradient-to-br from-primary-50 via-white to-primary-50 p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
                    >
                        <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-2xl bg-primary text-white shadow-md">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900">Release my music</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">
                            Best for artists, labels, and creators. Keep this tab selected, then complete the artist details before creating your account.
                        </p>
                        <span class="mt-4 inline-flex items-center gap-2 rounded-full bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition group-hover:bg-primary-500">
                            Continue as artist
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5l7 7-7 7" />
                            </svg>
                        </span>
                    </button>

                    <button
                        type="button"
                        x-on:click="$wire.set('role', 'listener'); showRoleGuide = false"
                        class="group rounded-[1.6rem] border border-gray-200 bg-gradient-to-br from-white via-slate-50 to-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
                    >
                        <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-900 text-white shadow-md">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900">Listen to music</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">
                            Best for fans and listeners. Switch to this tab and the artist-only fields disappear, so you can register with just the basics.
                        </p>
                        <span class="mt-4 inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition group-hover:border-primary-200 group-hover:text-primary-600">
                            Switch to listener
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5l7 7-7 7" />
                            </svg>
                        </span>
                    </button>
                </div>

                <div class="mt-5 flex items-center justify-between gap-3 border-t border-gray-100 pt-5 text-sm text-gray-500">
                    <p>Pick your path now, or close this guide and choose a tab manually above the form.</p>
                    <button
                        type="button"
                        class="shrink-0 rounded-full border border-gray-200 bg-white px-4 py-2 font-semibold text-gray-600 transition hover:border-gray-300 hover:text-gray-800"
                        x-on:click="showRoleGuide = false"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="mb-6 text-center">
        <h1 class="text-2xl font-bold text-gray-800">Create your account</h1>
        <p class="text-sm text-gray-500 mt-1">Join {{ $siteName }} today — it's free</p>
    </div>

    @if($errors->has('register'))
        <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ $errors->first('register') }}
        </div>
    @endif

    <form wire:submit="register" enctype="multipart/form-data" class="space-y-4">

        {{-- ── Role Toggle ──────────────────────────────────────────────────── --}}
        <div class="mb-6">
            <p class="text-sm font-medium text-gray-700 mb-2">{{ __('I want to…') }}</p>
            <div class="grid grid-cols-2 gap-3">
                <label class="flex flex-col items-center gap-1 p-3 rounded-xl border-2 cursor-pointer transition
                              {{ $role === 'listener' ? 'border-primary bg-primary-50' : 'border-gray-200 hover:border-primary-300' }}">
                    <input type="radio" wire:model.live="role" value="listener" class="sr-only">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 {{ $role === 'listener' ? 'text-primary' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                    </svg>
                    <span class="text-sm font-semibold {{ $role === 'listener' ? 'text-primary-600' : 'text-gray-600' }}">{{ __('Listen to music') }}</span>
                </label>

                <label class="flex flex-col items-center gap-1 p-3 rounded-xl border-2 cursor-pointer transition
                              {{ $role === 'artist' ? 'border-primary bg-primary-50' : 'border-gray-200 hover:border-primary-300' }}">
                    <input type="radio" wire:model.live="role" value="artist" class="sr-only">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 {{ $role === 'artist' ? 'text-primary' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                    <span class="text-sm font-semibold {{ $role === 'artist' ? 'text-primary-600' : 'text-gray-600' }}">{{ __('Release my music') }}</span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>

        {{-- ── Name ──────────────────────────────────────────────────────────── --}}
        <div>
            <x-input-label for="name" :value="__('Full Name')" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        {{-- ── Email ─────────────────────────────────────────────────────────── --}}
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        {{-- ── Password ──────────────────────────────────────────────────────── --}}
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        {{-- ── Artist Fields ─────────────────────────────────────────────────── --}}
        @if($role === 'artist')
        <div class="mt-6 space-y-4 rounded-xl border border-primary-200 bg-primary-50/60 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-primary">{{ __('Artist Details') }}</p>

            {{-- Stage Name --}}
            <div>
                <x-input-label for="stage_name" :value="__('Stage / Artist Name')" />
                <x-text-input wire:model="stage_name" id="stage_name" class="block mt-1 w-full" type="text" name="stage_name" placeholder="e.g. DJ Phoenix" />
                <x-input-error :messages="$errors->get('stage_name')" class="mt-2" />
            </div>

            {{-- Bio --}}
            <div>
                <x-input-label for="bio" :value="__('Bio') . ' (' . mb_strlen($bio) . '/500)'" />
                <textarea wire:model.live="bio" id="bio" name="bio" rows="3" maxlength="500"
                          class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-primary-400 focus:ring-primary-400 text-sm resize-none"
                          placeholder="{{ __('Tell the world about your music…') }}"></textarea>
                <x-input-error :messages="$errors->get('bio')" class="mt-2" />
            </div>

            {{-- Genres --}}
            @if(!empty($genres))
            <div>
                <x-input-label :value="__('Genres (up to 5)')" />
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($genres as $genre)
                    <label class="flex items-center gap-1 px-3 py-1 rounded-full border text-xs font-medium cursor-pointer transition
                                  {{ in_array($genre['id'], $selectedGenres) ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 border-gray-300 hover:border-primary-400' }}">
                        <input type="checkbox" wire:model.live="selectedGenres" value="{{ $genre['id'] }}" class="sr-only">
                        {{ $genre['name'] }}
                    </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('selectedGenres')" class="mt-2" />
            </div>
            @endif

            {{-- Avatar --}}
            <div>
                <x-input-label for="avatar" :value="__('Profile Photo (optional)')" />
                <div class="mt-1 flex items-center gap-4">
                    @if($avatar)
                        <img src="{{ $avatar->temporaryUrl() }}" class="h-16 w-16 rounded-full object-cover ring-2 ring-primary-400" alt="Preview">
                    @else
                        <div class="h-16 w-16 rounded-full bg-gray-200 flex items-center justify-center text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                    @endif
                    <input wire:model="avatar" id="avatar" type="file" accept="image/jpeg,image/png,image/webp"
                           class="text-sm text-gray-600 file:mr-3 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-600 hover:file:bg-primary-100">
                </div>
                <p class="mt-2 text-xs text-gray-400">JPG, PNG, or WEBP up to {{ UploadLimits::formatKilobytes(UploadLimits::imageKb()) }}.</p>
                <x-input-error :messages="$errors->get('avatar')" class="mt-2" />
            </div>
        </div>
        @endif

        {{-- ── Captcha + Submit ─────────────────────────────────────── --}}
        <x-math-captcha wire:model="captcha" error-bag="captcha" />

        <div class="rounded-2xl border border-gray-200 bg-white/75 p-4 shadow-sm">
            <label for="hasAcceptedPolicies" class="flex items-start gap-3 cursor-pointer">
                <input
                    wire:model.live="hasAcceptedPolicies"
                    id="hasAcceptedPolicies"
                    type="checkbox"
                    class="mt-1 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary-400"
                >
                <span class="text-sm leading-6 text-gray-600">
                    I have read and agree to the GrinMuzik
                    <a href="{{ route('terms') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-primary hover:text-primary-600 hover:underline">Terms of Service</a>.
                </span>
            </label>
            <x-input-error :messages="$errors->get('hasAcceptedPolicies')" class="mt-3" />
        </div>

        <div class="pt-2">
            <x-primary-button class="w-full justify-center">
                {{ __('Create Account') }}
            </x-primary-button>
        </div>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        Already have an account?
        <a href="{{ route('login') }}" wire:navigate class="text-red-500 font-medium hover:underline">Sign in</a>
    </p>
</div>
