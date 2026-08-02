<?php

use App\Mail\AccountDeletionRequestMail;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Rules\Captcha;
use App\Rules\Honeypot;
use App\Services\AccountDeletionService;
use App\Support\AuthThrottle;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Public account-deletion request page.
 *
 * Google Play requires a deletion route that a signed-out person can reach from
 * a browser without installing the app, which is why this is not simply the
 * button on the profile page.
 *
 * Because it is public, the email address on submission proves nothing — anyone
 * can type anyone's. So nothing is destroyed here: a signed link goes to the
 * mailbox, and the account is deleted only when that link comes back.
 */
new #[Layout('layouts.glass-app')] class extends Component
{
    public string $email = '';
    public string $captcha = '';
    public string $website = '';
    public bool $submitted = false;

    public function with(): array
    {
        $service = app(AccountDeletionService::class);

        return [
            'seo' => [
                'title' => 'Delete your account',
                'description' => 'Request permanent deletion of your GrinMuzik account and data.',
                'canonical' => route('account.deletion'),
                'robots' => 'index,follow',
            ],
            'deletedItems' => $service->deletedItems(),
            'retainedItems' => $service->retainedItems(),
        ];
    }

    public function requestDeletion(): void
    {
        AuthThrottle::ensureAllowed('account_deletion', 'email');

        $this->validate([
            'email' => ['required', 'string', 'email', 'max:200'],
            'captcha' => ['required', new Captcha('account-deletion')],
            'website' => [new Honeypot],
        ]);

        $user = User::where('email', $this->email)->first();

        if ($user) {
            [$request, $token] = AccountDeletionRequest::issueFor($user, request()->ip());

            Mail::to($user->email)->send(new AccountDeletionRequestMail(
                recipientName: $user->name,
                confirmUrl: route('account.deletion.confirm', ['token' => $token]),
                expiresInHours: (int) config('security.account_deletion.token_hours', 24),
            ));
        }

        // Same outcome either way. Reporting "no such account" would turn this
        // page into an account-existence oracle for any address someone cares
        // to try.
        $this->submitted = true;
        $this->reset('email', 'captcha', 'website');
    }
}; ?>

<div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Delete your GrinMuzik account</h1>

        <p class="mt-2 text-gray-600 dark:text-gray-300">
            You can permanently delete your account and its data at any time. If
            you are signed in you can also do this from
            <a href="{{ route('profile') }}" class="font-medium text-primary-600 hover:underline">your profile settings</a>.
        </p>

        @if ($submitted)
            <div class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">
                <p class="font-semibold">Check your email.</p>
                <p class="mt-1 text-sm">
                    If an account exists for that address, we've sent a
                    confirmation link. Your account will not be deleted until you
                    open it. The link expires in
                    {{ (int) config('security.account_deletion.token_hours', 24) }} hours.
                </p>
            </div>
        @else
            <form wire:submit="requestDeletion" class="mt-6 space-y-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <div>
                    <x-input-label for="email" :value="__('Your account email')" />
                    <x-text-input
                        wire:model="email"
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                        autocomplete="email"
                        placeholder="you@example.com"
                    />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <x-captcha-field model="captcha" honeypot="website" form-key="account-deletion" />

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    We'll email you a link to confirm. Nothing is deleted until
                    you open it.
                </p>

                <x-danger-button class="w-full justify-center">
                    <span wire:loading.remove wire:target="requestDeletion">Request account deletion</span>
                    <span wire:loading wire:target="requestDeletion">Sending...</span>
                </x-danger-button>
            </form>
        @endif

        <div class="mt-8 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">What gets deleted</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-gray-600 dark:text-gray-300">
                @foreach ($deletedItems as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>

            <h2 class="mt-6 text-lg font-semibold text-gray-900 dark:text-white">What we keep, and why</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-gray-600 dark:text-gray-300">
                @foreach ($retainedItems as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>

            <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">
                Deletion is immediate and cannot be undone. See our
                <a href="{{ route('privacy') }}" class="font-medium text-primary-600 hover:underline">Privacy Policy</a>
                for how we handle your data more generally.
            </p>
        </div>
    </div>
</div>
