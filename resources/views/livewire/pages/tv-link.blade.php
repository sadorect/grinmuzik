<?php

use App\Models\TvDeviceCode;
use App\Support\AuthThrottle;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

/**
 * Where a listener approves the code their TV is showing.
 *
 * Two steps on purpose: typing the code only finds the TV, and nothing is
 * signed in until the person sees which device is asking and presses Approve.
 * That way someone who reads a code out over the phone ("just type this in")
 * still gets a screen saying what they are about to hand over.
 */
new #[Layout('layouts.glass-app')] class extends Component
{
    #[Url(as: 'code', except: '')]
    public string $code = '';

    #[Locked]
    public ?int $pendingId = null;

    public ?string $outcome = null;

    public function with(): array
    {
        $pending = $this->pendingId ? TvDeviceCode::find($this->pendingId) : null;

        return [
            'seo' => [
                'title' => 'Sign in on your TV',
                'description' => 'Approve the code shown on your TV to sign in to GrinMuzik.',
                'canonical' => route('tv.link'),
                'robots' => 'noindex,nofollow',
            ],
            'pending' => $pending,
            'tvs' => auth()->user()->tokens()
                ->where('name', 'like', TvDeviceCode::TOKEN_NAME_PREFIX.'%')
                ->latest()
                ->get(),
        ];
    }

    public function findCode(): void
    {
        $this->outcome = null;

        $this->validate(['code' => ['required', 'string', 'max:20']]);

        AuthThrottle::ensureAllowed('tv_code_entry', 'code');

        $found = TvDeviceCode::findPendingByUserCode($this->code);

        if (! $found) {
            $this->addError('code', "That code didn't work. Check the TV: codes expire after a few minutes, so you may need a new one.");

            return;
        }

        $this->pendingId = $found->id;
    }

    public function approve(): void
    {
        $pending = $this->pendingId ? TvDeviceCode::find($this->pendingId) : null;

        $this->outcome = $pending && $pending->approve(auth()->user()) ? 'approved' : 'expired';
        $this->reset('pendingId', 'code');
    }

    public function deny(): void
    {
        TvDeviceCode::find($this->pendingId)?->deny();

        $this->outcome = 'denied';
        $this->reset('pendingId', 'code');
    }

    public function revoke(int $tokenId): void
    {
        auth()->user()->tokens()
            ->whereKey($tokenId)
            ->where('name', 'like', TvDeviceCode::TOKEN_NAME_PREFIX.'%')
            ->delete();
    }
}; ?>

<div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-lg">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Sign in on your TV</h1>

        @if ($outcome === 'approved')
            <div class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">
                <p class="font-semibold">You're signed in on your TV.</p>
                <p class="mt-1 text-sm">It should switch over within a few seconds.</p>
            </div>
        @elseif ($outcome === 'denied')
            <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                <p class="font-semibold">Request declined.</p>
                <p class="mt-1 text-sm">That TV has not been signed in to your account.</p>
            </div>
        @elseif ($outcome === 'expired')
            <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                <p class="font-semibold">That code has expired.</p>
                <p class="mt-1 text-sm">Get a new code on the TV and try again.</p>
            </div>
        @endif

        @if ($pending)
            <div class="mt-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-gray-600 dark:text-gray-300">
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $pending->device_name ?: 'A TV' }}</span>
                    wants to sign in to your GrinMuzik account
                    (<span class="font-medium">{{ auth()->user()->email }}</span>).
                </p>
                <p class="mt-3 font-mono text-2xl tracking-widest text-gray-900 dark:text-white">{{ $pending->displayUserCode() }}</p>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    Only approve if this code is on a TV in front of you. Nobody
                    from GrinMuzik will ever ask you to type in a code.
                </p>

                <div class="mt-6 flex gap-3">
                    <x-primary-button wire:click="approve" class="flex-1 justify-center">Approve</x-primary-button>
                    <x-secondary-button wire:click="deny" class="flex-1 justify-center">Decline</x-secondary-button>
                </div>
            </div>
        @else
            <p class="mt-2 text-gray-600 dark:text-gray-300">
                Open GrinMuzik on your TV, choose Sign in, and type the code it shows.
            </p>

            <form wire:submit="findCode" class="mt-6 space-y-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <div>
                    <x-input-label for="code" :value="__('Code on your TV')" />
                    <x-text-input
                        wire:model="code"
                        id="code"
                        type="text"
                        class="mt-1 block w-full font-mono text-xl uppercase tracking-widest"
                        required
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        placeholder="ABCD-EFGH"
                    />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>

                <x-primary-button class="w-full justify-center">
                    <span wire:loading.remove wire:target="findCode">Continue</span>
                    <span wire:loading wire:target="findCode">Checking...</span>
                </x-primary-button>
            </form>
        @endif

        @if ($tvs->isNotEmpty())
            <div class="mt-8 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">TVs signed in to your account</h2>
                <ul class="mt-3 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($tvs as $tv)
                        <li class="flex items-center justify-between gap-3 py-3" wire:key="tv-{{ $tv->id }}">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-gray-900 dark:text-white">{{ \Illuminate\Support\Str::after($tv->name, \App\Models\TvDeviceCode::TOKEN_NAME_PREFIX) }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Signed in {{ $tv->created_at->diffForHumans() }}
                                    @if ($tv->last_used_at) · last used {{ $tv->last_used_at->diffForHumans() }} @endif
                                </p>
                            </div>
                            <button type="button"
                                    wire:click="revoke({{ $tv->id }})"
                                    wire:confirm="Sign this TV out of your account?"
                                    class="shrink-0 text-sm font-medium text-red-600 hover:underline dark:text-red-400">
                                Sign out
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
