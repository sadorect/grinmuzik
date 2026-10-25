@props(['at'])

{{-- Server renders UTC; Alpine rewrites it in the viewer's own timezone. --}}
@php($utc = \Illuminate\Support\Carbon::instance($at)->utc())
<time
    datetime="{{ $utc->toIso8601ZuluString() }}"
    x-data
    x-text="new Date($el.getAttribute('datetime')).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })"
    {{ $attributes }}
>{{ $utc->format('j M Y, H:i') }} UTC</time>
