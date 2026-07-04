<x-filament-panels::page>
    <form wire:submit="generate" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="generate">
            <span wire:loading.remove wire:target="generate">Generate Draft</span>
            <span wire:loading wire:target="generate">Generating your newsletter...</span>
        </x-filament::button>
    </form>

    @if ($currentDraftId)
        <div class="mt-8 space-y-4 rounded-xl border border-gray-200 p-6 dark:border-gray-700">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $draftSubject }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $draftPeriod }}</p>
                </div>
                <span @class([
                    'rounded-full px-3 py-1 text-xs font-semibold',
                    'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => $draftStatus === 'draft',
                    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $draftStatus === 'approved',
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $draftStatus === 'sent',
                ])>
                    {{ ucfirst($draftStatus) }}
                </span>
            </div>

            <textarea
                wire:model="draftBody"
                rows="16"
                @disabled($draftStatus !== 'draft')
                class="w-full rounded-lg border-gray-300 font-mono text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
            ></textarea>

            <div class="flex items-center gap-3">
                <x-filament::button
                    color="warning"
                    wire:click="approve"
                    :disabled="$draftStatus !== 'draft'"
                >
                    Approve
                </x-filament::button>

                <x-filament::button
                    color="success"
                    wire:click="sendNewsletter"
                    :disabled="$draftStatus !== 'approved'"
                >
                    Send Newsletter
                </x-filament::button>
            </div>

            @if ($draftStatus === 'sent')
                <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
                    This newsletter has been sent to subscribers.
                </div>
            @endif
        </div>
    @endif

    <div class="mt-10">
        <h3 class="mb-4 text-lg font-semibold text-gray-950 dark:text-white">Draft History</h3>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <th class="py-2 pr-4 font-medium">Period</th>
                        <th class="py-2 pr-4 font-medium">Subject</th>
                        <th class="py-2 pr-4 font-medium">Status</th>
                        <th class="py-2 pr-4 font-medium">Generated At</th>
                        <th class="py-2 pr-4 font-medium">Sent At</th>
                        <th class="py-2 pr-4 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->recentDrafts as $draft)
                        <tr wire:key="draft-{{ $draft->id }}" class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $draft->period }}</td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $draft->subject }}</td>
                            <td class="py-2 pr-4">
                                <span @class([
                                    'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => $draft->status === 'draft',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $draft->status === 'approved',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $draft->status === 'sent',
                                ])>
                                    {{ ucfirst($draft->status) }}
                                </span>
                            </td>
                            <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ $draft->generated_at?->format('M j, Y H:i') ?? '—' }}</td>
                            <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ $draft->sent_at?->format('M j, Y H:i') ?? '—' }}</td>
                            <td class="py-2 pr-4">
                                <div class="flex items-center gap-2">
                                    <x-filament::button size="xs" color="gray" wire:click="viewDraft({{ $draft->id }})">
                                        View
                                    </x-filament::button>
                                    @if ($draft->isDraft())
                                        <x-filament::button size="xs" color="warning" wire:click="approveDraft({{ $draft->id }})">
                                            Approve
                                        </x-filament::button>
                                    @endif
                                    @if ($draft->isSent())
                                        <x-filament::button size="xs" color="success" wire:click="resendDraft({{ $draft->id }})">
                                            Re-send
                                        </x-filament::button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-gray-500 dark:text-gray-400">
                                No newsletters generated yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
