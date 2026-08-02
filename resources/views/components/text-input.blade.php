@props(['disabled' => false])

{{-- See the note in input-label: this is used on dark-capable glass-app pages
     as well as the light-only auth pages. --}}
<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white/60 text-gray-800 placeholder-gray-400 dark:border-gray-600 dark:bg-gray-900/60 dark:text-gray-100 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-400 focus:border-transparent text-sm transition']) }}>
