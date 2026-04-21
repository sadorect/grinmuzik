<?php

use App\Models\InternalMessage;
use App\Models\InternalMessageRead;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.glass-app')] class extends Component
{
    public ?int $openMessageId = null;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function with(): array
    {
        $user = auth()->user();

        $messages = InternalMessage::query()
            ->sent()
            ->with(['reads' => fn ($q) => $q->where('user_id', $user->id)])
            ->latest('sent_at')
            ->get();

        $messages = $messages
            ->filter(fn (InternalMessage $message): bool => $message->targetsUser($user))
            ->values();

        return [
            'messages'      => $messages,
            'unreadCount'   => $messages->filter(fn ($m) => $m->reads->isEmpty())->count(),
        ];
    }

    public function open(int $id): void
    {
        $user = auth()->user();
        $message = InternalMessage::sent()->findOrFail($id);
        abort_unless($message->targetsUser($user), 404);

        $this->openMessageId = $id;

        // Mark as read
        InternalMessageRead::firstOrCreate(
            ['internal_message_id' => $id, 'user_id' => $user->id],
            ['read_at' => now()],
        );
    }

    public function close(): void
    {
        $this->openMessageId = null;
    }

    public function markAllRead(): void
    {
        $user = auth()->user();

        InternalMessage::forUser($user)
            ->get()
            ->each(function (InternalMessage $message) use ($user): void {
                InternalMessageRead::firstOrCreate(
                    ['internal_message_id' => $message->id, 'user_id' => $user->id],
                    ['read_at' => now()],
                );
            });
    }
}; ?>

<div class="max-w-4xl mx-auto px-4 py-8 space-y-6">

    {{-- Header --}}
    <section class="glass-card rounded-[2rem] p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">Inbox</h1>
                <p class="mt-1 text-sm text-slate-500">Messages from the {{ config('app.name') }} team</p>
            </div>
            @if($unreadCount > 0)
                <button
                    wire:click="markAllRead"
                    class="self-start sm:self-auto inline-flex items-center gap-2 rounded-xl border border-primary/30 bg-primary/10 px-4 py-2 text-sm font-semibold text-primary-700 transition hover:bg-primary/20"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Mark all as read
                </button>
            @endif
        </div>
    </section>

    {{-- Message list --}}
    <section class="glass-card rounded-[2rem] overflow-hidden">
        @forelse($messages as $msg)
            @php($isRead = $msg->reads->isNotEmpty())
            @php($isOpen = $openMessageId === $msg->id)

            <div
                wire:key="msg-{{ $msg->id }}"
                class="border-b border-slate-100 last:border-b-0"
            >
                {{-- Row --}}
                <button
                    wire:click="{{ $isOpen ? 'close' : 'open(' . $msg->id . ')' }}"
                    class="w-full flex items-start gap-4 px-6 py-4 text-left transition hover:bg-primary-50/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/60"
                >
                    {{-- Unread dot --}}
                    <span class="mt-1.5 flex-shrink-0 h-2.5 w-2.5 rounded-full {{ $isRead ? 'bg-transparent' : 'bg-primary-500' }}"></span>

                    <div class="flex-1 min-w-0">
                        <p class="text-sm {{ $isRead ? 'font-medium text-slate-600' : 'font-bold text-slate-900' }} truncate">
                            {{ $msg->subject }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{ $msg->sent_at->diffForHumans() }} ·
                            @if($msg->recipient_user_id)
                                <span class="font-semibold text-primary-600">Direct message</span>
                            @else
                                {{ $msg->audienceLabel() }}
                            @endif
                        </p>
                    </div>

                    <svg class="h-4 w-4 flex-shrink-0 text-slate-400 transition-transform {{ $isOpen ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                {{-- Expanded body --}}
                @if($isOpen)
                    <div class="px-6 pb-6 pt-2">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="prose prose-sm max-w-none text-slate-700">{!! $msg->interpolate($msg->body, auth()->user()) !!}</div>
                        </div>
                        <p class="mt-3 text-xs text-slate-400">Sent {{ $msg->sent_at->format('d M Y, H:i') }}</p>
                    </div>
                @endif
            </div>
        @empty
            <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                <svg class="h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <p class="mt-4 font-semibold text-slate-500">Your inbox is empty</p>
                <p class="mt-1 text-sm text-slate-400">Messages from the team will appear here.</p>
            </div>
        @endforelse
    </section>

</div>
