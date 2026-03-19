@php($class = $class ?? '')
@php($showLabel = $showLabel ?? false)
@php($themeToggleEnabled = $showPublicThemeToggle ?? ($siteSettings?->effective_show_public_theme_toggle ?? true))

@if($themeToggleEnabled)
    <button
        type="button"
        data-theme-toggle
        onclick="window.GrinTheme?.toggle()"
        class="theme-toggle {{ $class }}"
        aria-label="Toggle color theme"
    >
        <span class="theme-toggle-icon">
            <svg data-theme-icon="moon" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z" />
            </svg>
            <svg data-theme-icon="sun" class="hidden h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2.25m0 13.5V21m9-9h-2.25M5.25 12H3m15.114 6.364-1.591-1.59M7.477 7.477l-1.59-1.59m12.227 0-1.591 1.59M7.477 16.523l-1.59 1.59M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
            </svg>
        </span>

        @if($showLabel)
            <span data-theme-label class="whitespace-nowrap">Dark mode</span>
        @endif
    </button>
@endif
