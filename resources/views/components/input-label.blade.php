@props(['value'])

{{-- The dark variant matters even though the auth pages are light-only: this
     component is also used on glass-app pages (account deletion, the report
     modal), which follow the site's class-based dark mode. --}}
<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-gray-700 dark:text-gray-200 mb-1']) }}>
    {{ $value ?? $slot }}
</label>
