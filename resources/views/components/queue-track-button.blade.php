@props([
    'track',
    'label' => 'Queue',
    'srLabel' => null,
])

@php
    $accessibleLabel = $srLabel ?: 'Add ' . ($track->title ?? 'track') . ' to queue';
    $iconOnly = $label === '+' || blank($label);
@endphp

<button
    type="button"
    @click.stop="Livewire.dispatch('queue-track', { id: {{ $track->id }} })"
    {{ $attributes->class([
        'inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 shadow-sm transition hover:border-primary hover:text-primary',
    ]) }}
>
    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary/10 text-primary">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m7-7H5"/>
        </svg>
    </span>
    @if($iconOnly)
        <span class="sr-only">{{ $accessibleLabel }}</span>
    @else
        <span>{{ $label }}</span>
    @endif
</button>
