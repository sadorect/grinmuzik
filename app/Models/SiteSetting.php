<?php

namespace App\Models;

use App\Support\ThemePalette;
use App\Support\UploadLimits;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SiteSetting extends Model
{
    protected static ?bool $supportsDiscoveryVisibilityCache = null;
    protected static ?bool $supportsFeaturedAlbumsCache = null;
    protected static ?bool $supportsDiscoveryOrderingCache = null;
    protected static ?bool $supportsUploadLimitsCache = null;
    protected static ?bool $supportsMonetizationControlsCache = null;
    protected static ?bool $supportsCurrencyControlsCache = null;
    protected static ?string $platformCurrencyCache = null;
    protected static ?float $platformFeePercentCache = null;
    protected static ?bool $supportsPaymentFeeControlCache = null;
    protected static ?bool $supportsThemeCustomizationCache = null;
    protected static ?bool $supportsPublicThemeToggleControlCache = null;
    protected static ?bool $supportsAutoPlaylistControlsCache = null;
    protected static ?bool $supportsAutoQueueLimitsCache = null;
    protected static ?bool $supportsRegistrationRoleGuideControlCache = null;
    protected static ?bool $supportsRegistrationVerificationControlCache = null;
    protected static ?bool $paymentsEnabledCache = null;
    protected static ?bool $autoPlaylistEnabledCache = null;
    protected static ?int $autoQueueTotalLimitCache = null;
    protected static ?int $autoQueueAlbumLimitCache = null;
    protected static ?int $autoQueueArtistLimitCache = null;
    protected static ?int $autoQueueGenreLimitCache = null;
    protected static ?bool $registrationRequiresEmailVerificationCache = null;

    protected $fillable = [
        'site_name',
        'site_title',
        'x_handle',
        'instagram_handle',
        'facebook_handle',
        'tiktok_handle',
        'youtube_handle',
        'site_logo',
        'favicon',
        'audio_upload_limit_kb',
        'image_upload_limit_kb',
        'site_logo_upload_limit_kb',
        'favicon_upload_limit_kb',
        'hero_image_upload_limit_kb',
        'show_home_personalized',
        'show_home_editor_picks',
        'show_home_featured_albums',
        'show_browse_mood_filters',
        'show_browse_editor_picks',
        'show_browse_personalized',
        'show_browse_fresh_this_week',
        'show_browse_artists_to_watch',
        'show_browse_support_direct',
        'show_search_trending_tracks',
        'show_search_popular_artists',
        'home_editor_picks_position',
        'browse_editor_picks_position',
        'payments_enabled',
        'platform_currency',
        'platform_fee_percent',
        'primary_color',
        'secondary_color',
        'show_public_theme_toggle',
        'auto_playlist_enabled',
        'auto_queue_total_limit',
        'auto_queue_album_limit',
        'auto_queue_artist_limit',
        'auto_queue_genre_limit',
        'show_registration_role_guide',
        'registration_requires_email_verification',
    ];

    protected $casts = [
        'show_home_personalized' => 'boolean',
        'show_home_editor_picks' => 'boolean',
        'show_home_featured_albums' => 'boolean',
        'audio_upload_limit_kb' => 'integer',
        'image_upload_limit_kb' => 'integer',
        'site_logo_upload_limit_kb' => 'integer',
        'favicon_upload_limit_kb' => 'integer',
        'hero_image_upload_limit_kb' => 'integer',
        'show_browse_mood_filters' => 'boolean',
        'show_browse_editor_picks' => 'boolean',
        'show_browse_personalized' => 'boolean',
        'show_browse_fresh_this_week' => 'boolean',
        'show_browse_artists_to_watch' => 'boolean',
        'show_browse_support_direct' => 'boolean',
        'show_search_trending_tracks' => 'boolean',
        'show_search_popular_artists' => 'boolean',
        'payments_enabled' => 'boolean',
        'platform_currency' => 'string',
        'platform_fee_percent' => 'decimal:2',
        'show_public_theme_toggle' => 'boolean',
        'auto_playlist_enabled' => 'boolean',
        'auto_queue_total_limit' => 'integer',
        'auto_queue_album_limit' => 'integer',
        'auto_queue_artist_limit' => 'integer',
        'auto_queue_genre_limit' => 'integer',
        'show_registration_role_guide' => 'boolean',
        'registration_requires_email_verification' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): bool => static::flushRuntimeCaches());
        static::deleted(fn (): bool => static::flushRuntimeCaches());
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public static function supportsDiscoveryVisibility(): bool
    {
        if (static::$supportsDiscoveryVisibilityCache !== null) {
            return static::$supportsDiscoveryVisibilityCache;
        }

        try {
            if (! Schema::hasTable('site_settings')) {
                return static::$supportsDiscoveryVisibilityCache = false;
            }

            return static::$supportsDiscoveryVisibilityCache = Schema::hasColumn('site_settings', 'show_home_personalized');
        } catch (Throwable) {
            return static::$supportsDiscoveryVisibilityCache = false;
        }
    }

    public static function supportsFeaturedAlbums(): bool
    {
        if (static::$supportsFeaturedAlbumsCache !== null) {
            return static::$supportsFeaturedAlbumsCache;
        }

        try {
            if (! Schema::hasTable('site_settings')) {
                return static::$supportsFeaturedAlbumsCache = false;
            }

            return static::$supportsFeaturedAlbumsCache = Schema::hasColumn('site_settings', 'show_home_featured_albums');
        } catch (Throwable) {
            return static::$supportsFeaturedAlbumsCache = false;
        }
    }

    public static function showHomeFeaturedAlbums(): bool
    {
        if (! static::supportsFeaturedAlbums()) {
            return false;
        }

        try {
            return (bool) (static::current()->show_home_featured_albums ?? false);
        } catch (Throwable) {
            return false;
        }
    }

    public static function supportsDiscoveryOrdering(): bool
    {
        if (static::$supportsDiscoveryOrderingCache !== null) {
            return static::$supportsDiscoveryOrderingCache;
        }

        try {
            if (! Schema::hasTable('site_settings')) {
                return static::$supportsDiscoveryOrderingCache = false;
            }

            return static::$supportsDiscoveryOrderingCache = Schema::hasColumn('site_settings', 'home_editor_picks_position');
        } catch (Throwable) {
            return static::$supportsDiscoveryOrderingCache = false;
        }
    }

    public static function supportsUploadLimits(): bool
    {
        if (static::$supportsUploadLimitsCache !== null) {
            return static::$supportsUploadLimitsCache;
        }

        try {
            if (! Schema::hasTable('site_settings')) {
                return static::$supportsUploadLimitsCache = false;
            }

            return static::$supportsUploadLimitsCache = Schema::hasColumn('site_settings', 'audio_upload_limit_kb');
        } catch (Throwable) {
            return static::$supportsUploadLimitsCache = false;
        }
    }

    public static function supportsMonetizationControls(): bool
    {
        if (static::$supportsMonetizationControlsCache !== null) {
            return static::$supportsMonetizationControlsCache;
        }

        try {
            if (! Schema::hasTable('site_settings')) {
                return static::$supportsMonetizationControlsCache = false;
            }

            return static::$supportsMonetizationControlsCache = Schema::hasColumn('site_settings', 'payments_enabled');
        } catch (Throwable) {
            return static::$supportsMonetizationControlsCache = false;
        }
    }

    public static function supportsCurrencyControls(): bool
    {
        if (static::$supportsCurrencyControlsCache !== null) {
            return static::$supportsCurrencyControlsCache;
        }

        try {
            if (! Schema::hasTable('site_settings')) {
                return static::$supportsCurrencyControlsCache = false;
            }

            return static::$supportsCurrencyControlsCache = Schema::hasColumn('site_settings', 'platform_currency');
        } catch (Throwable) {
            return static::$supportsCurrencyControlsCache = false;
        }
    }

    public static function platformCurrency(): string
    {
        if (static::$platformCurrencyCache !== null) {
            return static::$platformCurrencyCache;
        }

        if (! static::supportsCurrencyControls()) {
            return static::$platformCurrencyCache = 'NGN';
        }

        try {
            $value = static::current()->platform_currency;

            return static::$platformCurrencyCache = filled($value) ? strtoupper($value) : 'NGN';
        } catch (Throwable) {
            return static::$platformCurrencyCache = 'NGN';
        }
    }

    public static function supportsPaymentFeeControl(): bool
    {
        if (static::$supportsPaymentFeeControlCache !== null) {
            return static::$supportsPaymentFeeControlCache;
        }

        try {
            if (! Schema::hasTable('site_settings')) {
                return static::$supportsPaymentFeeControlCache = false;
            }

            return static::$supportsPaymentFeeControlCache = Schema::hasColumn('site_settings', 'platform_fee_percent');
        } catch (Throwable) {
            return static::$supportsPaymentFeeControlCache = false;
        }
    }

    /** Platform fee percentage retained from each Connect destination charge (0–100). */
    public static function platformFeePercent(): float
    {
        if (static::$platformFeePercentCache !== null) {
            return static::$platformFeePercentCache;
        }

        if (! static::supportsPaymentFeeControl()) {
            return static::$platformFeePercentCache = 5.0;
        }

        try {
            $value = (float) (static::current()->platform_fee_percent ?? 5.0);

            return static::$platformFeePercentCache = max(0.0, min(100.0, $value));
        } catch (Throwable) {
            return static::$platformFeePercentCache = 5.0;
        }
    }

    public static function supportsThemeCustomization(): bool
    {
        if (static::$supportsThemeCustomizationCache !== null) {
            return static::$supportsThemeCustomizationCache;
        }

        try {
            if (! Schema::hasTable('site_settings')) {
                return static::$supportsThemeCustomizationCache = false;
            }

            return static::$supportsThemeCustomizationCache = Schema::hasColumn('site_settings', 'primary_color')
                && Schema::hasColumn('site_settings', 'secondary_color');
        } catch (Throwable) {
            return static::$supportsThemeCustomizationCache = false;
        }
    }

    public static function supportsPublicThemeToggleControl(): bool
    {
        if (static::$supportsPublicThemeToggleControlCache !== null) {
            return static::$supportsPublicThemeToggleControlCache;
        }

        try {
            if (! Schema::hasTable('site_settings')) {
                return static::$supportsPublicThemeToggleControlCache = false;
            }

            return static::$supportsPublicThemeToggleControlCache = Schema::hasColumn('site_settings', 'show_public_theme_toggle');
        } catch (Throwable) {
            return static::$supportsPublicThemeToggleControlCache = false;
        }
    }

    public static function supportsAutoPlaylistControls(): bool
    {
        if (static::$supportsAutoPlaylistControlsCache !== null) {
            return static::$supportsAutoPlaylistControlsCache;
        }

        try {
            if (! Schema::hasTable('site_settings')) {
                return static::$supportsAutoPlaylistControlsCache = false;
            }

            return static::$supportsAutoPlaylistControlsCache = Schema::hasColumn('site_settings', 'auto_playlist_enabled');
        } catch (Throwable) {
            return static::$supportsAutoPlaylistControlsCache = false;
        }
    }

    public static function supportsAutoQueueLimits(): bool
    {
        if (static::$supportsAutoQueueLimitsCache !== null) {
            return static::$supportsAutoQueueLimitsCache;
        }

        try {
            if (! Schema::hasTable('site_settings')) {
                return static::$supportsAutoQueueLimitsCache = false;
            }

            return static::$supportsAutoQueueLimitsCache = Schema::hasColumn('site_settings', 'auto_queue_total_limit');
        } catch (Throwable) {
            return static::$supportsAutoQueueLimitsCache = false;
        }
    }

    public static function supportsRegistrationRoleGuideControl(): bool
    {
        if (static::$supportsRegistrationRoleGuideControlCache !== null) {
            return static::$supportsRegistrationRoleGuideControlCache;
        }

        try {
            if (! Schema::hasTable('site_settings')) {
                return static::$supportsRegistrationRoleGuideControlCache = false;
            }

            return static::$supportsRegistrationRoleGuideControlCache = Schema::hasColumn('site_settings', 'show_registration_role_guide');
        } catch (Throwable) {
            return static::$supportsRegistrationRoleGuideControlCache = false;
        }
    }

    public static function supportsRegistrationVerificationControl(): bool
    {
        if (static::$supportsRegistrationVerificationControlCache !== null) {
            return static::$supportsRegistrationVerificationControlCache;
        }

        try {
            if (! Schema::hasTable('site_settings')) {
                return static::$supportsRegistrationVerificationControlCache = false;
            }

            return static::$supportsRegistrationVerificationControlCache = Schema::hasColumn('site_settings', 'registration_requires_email_verification');
        } catch (Throwable) {
            return static::$supportsRegistrationVerificationControlCache = false;
        }
    }

    public static function paymentsEnabled(): bool
    {
        if (static::$paymentsEnabledCache !== null) {
            return static::$paymentsEnabledCache;
        }

        if (! static::supportsMonetizationControls()) {
            return static::$paymentsEnabledCache = true;
        }

        try {
            return static::$paymentsEnabledCache = (bool) (static::current()->payments_enabled ?? false);
        } catch (Throwable) {
            return static::$paymentsEnabledCache = false;
        }
    }

    public static function autoPlaylistEnabled(): bool
    {
        if (static::$autoPlaylistEnabledCache !== null) {
            return static::$autoPlaylistEnabledCache;
        }

        if (! static::supportsAutoPlaylistControls()) {
            return static::$autoPlaylistEnabledCache = false;
        }

        try {
            return static::$autoPlaylistEnabledCache = (bool) (static::current()->auto_playlist_enabled ?? true);
        } catch (Throwable) {
            return static::$autoPlaylistEnabledCache = false;
        }
    }

    public static function autoQueueTotalLimit(): int
    {
        if (static::$autoQueueTotalLimitCache !== null) {
            return static::$autoQueueTotalLimitCache;
        }

        if (! static::supportsAutoQueueLimits()) {
            return static::$autoQueueTotalLimitCache = 10;
        }

        try {
            return static::$autoQueueTotalLimitCache = max(1, (int) (static::current()->auto_queue_total_limit ?? 10));
        } catch (Throwable) {
            return static::$autoQueueTotalLimitCache = 10;
        }
    }

    public static function autoQueueAlbumLimit(): int
    {
        if (static::$autoQueueAlbumLimitCache !== null) {
            return static::$autoQueueAlbumLimitCache;
        }

        if (! static::supportsAutoQueueLimits()) {
            return static::$autoQueueAlbumLimitCache = 2;
        }

        try {
            return static::$autoQueueAlbumLimitCache = max(0, (int) (static::current()->auto_queue_album_limit ?? 2));
        } catch (Throwable) {
            return static::$autoQueueAlbumLimitCache = 2;
        }
    }

    public static function autoQueueArtistLimit(): int
    {
        if (static::$autoQueueArtistLimitCache !== null) {
            return static::$autoQueueArtistLimitCache;
        }

        if (! static::supportsAutoQueueLimits()) {
            return static::$autoQueueArtistLimitCache = 3;
        }

        try {
            return static::$autoQueueArtistLimitCache = max(0, (int) (static::current()->auto_queue_artist_limit ?? 3));
        } catch (Throwable) {
            return static::$autoQueueArtistLimitCache = 3;
        }
    }

    public static function autoQueueGenreLimit(): int
    {
        if (static::$autoQueueGenreLimitCache !== null) {
            return static::$autoQueueGenreLimitCache;
        }

        if (! static::supportsAutoQueueLimits()) {
            return static::$autoQueueGenreLimitCache = 4;
        }

        try {
            return static::$autoQueueGenreLimitCache = max(0, (int) (static::current()->auto_queue_genre_limit ?? 4));
        } catch (Throwable) {
            return static::$autoQueueGenreLimitCache = 4;
        }
    }

    public static function registrationRequiresEmailVerification(): bool
    {
        if (static::$registrationRequiresEmailVerificationCache !== null) {
            return static::$registrationRequiresEmailVerificationCache;
        }

        if (! static::supportsRegistrationVerificationControl()) {
            return static::$registrationRequiresEmailVerificationCache = true;
        }

        try {
            return static::$registrationRequiresEmailVerificationCache = (bool) (static::current()->registration_requires_email_verification ?? true);
        } catch (Throwable) {
            return static::$registrationRequiresEmailVerificationCache = true;
        }
    }

    public function getSiteLogoUrlAttribute(): ?string
    {
        return $this->buildPublicAssetUrl($this->site_logo);
    }

    public function getFaviconUrlAttribute(): ?string
    {
        return $this->buildPublicAssetUrl($this->favicon);
    }

    public static function defaultImageUrl(): string
    {
        try {
            return trim((string) static::current()->site_logo_url);
        } catch (Throwable) {
            return '';
        }
    }

    protected function buildPublicAssetUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $url = Storage::disk(config('media-storage.public_disk', 'public'))->url($path);
        $version = $this->updated_at?->timestamp;

        return $version ? "{$url}?v={$version}" : $url;
    }

    public function getEffectiveSiteNameAttribute(): string
    {
        $siteName = trim((string) $this->site_name);

        return $siteName !== '' ? $siteName : 'GrinMuzik';
    }

    public function getEffectiveSiteTitleAttribute(): string
    {
        $siteTitle = trim((string) $this->site_title);

        return $siteTitle !== '' ? $siteTitle : $this->effective_site_name;
    }

    public function getEffectiveAudioUploadLimitKbAttribute(): int
    {
        return (int) ($this->audio_upload_limit_kb ?: UploadLimits::DEFAULT_AUDIO_KB);
    }

    public function getEffectiveImageUploadLimitKbAttribute(): int
    {
        return (int) ($this->image_upload_limit_kb ?: UploadLimits::DEFAULT_IMAGE_KB);
    }

    public function getEffectiveSiteLogoUploadLimitKbAttribute(): int
    {
        return (int) ($this->site_logo_upload_limit_kb ?: UploadLimits::DEFAULT_SITE_LOGO_KB);
    }

    public function getEffectiveFaviconUploadLimitKbAttribute(): int
    {
        return (int) ($this->favicon_upload_limit_kb ?: UploadLimits::DEFAULT_FAVICON_KB);
    }

    public function getEffectiveHeroImageUploadLimitKbAttribute(): int
    {
        return (int) ($this->hero_image_upload_limit_kb ?: UploadLimits::DEFAULT_HERO_IMAGE_KB);
    }

    public function getEffectivePaymentsEnabledAttribute(): bool
    {
        if (! static::supportsMonetizationControls()) {
            return true;
        }

        return (bool) ($this->payments_enabled ?? false);
    }

    public function getEffectivePlatformCurrencyAttribute(): string
    {
        if (! static::supportsCurrencyControls()) {
            return 'NGN';
        }

        return filled($this->platform_currency) ? strtoupper((string) $this->platform_currency) : 'NGN';
    }

    public function getEffectivePrimaryColorAttribute(): string
    {
        return ThemePalette::normalize(
            static::supportsThemeCustomization() ? $this->primary_color : null,
            ThemePalette::DEFAULT_PRIMARY,
        );
    }

    public function getEffectiveSecondaryColorAttribute(): string
    {
        return ThemePalette::normalize(
            static::supportsThemeCustomization() ? $this->secondary_color : null,
            ThemePalette::DEFAULT_SECONDARY,
        );
    }

    public function getEffectiveAutoPlaylistEnabledAttribute(): bool
    {
        if (! static::supportsAutoPlaylistControls()) {
            return false;
        }

        return (bool) ($this->auto_playlist_enabled ?? true);
    }

    public function getEffectiveShowPublicThemeToggleAttribute(): bool
    {
        if (! static::supportsPublicThemeToggleControl()) {
            return true;
        }

        return (bool) ($this->show_public_theme_toggle ?? true);
    }

    public function getEffectiveShowRegistrationRoleGuideAttribute(): bool
    {
        if (! static::supportsRegistrationRoleGuideControl()) {
            return true;
        }

        return (bool) ($this->show_registration_role_guide ?? true);
    }

    public function getEffectiveRegistrationRequiresEmailVerificationAttribute(): bool
    {
        if (! static::supportsRegistrationVerificationControl()) {
            return true;
        }

        return (bool) ($this->registration_requires_email_verification ?? true);
    }

    /**
     * @return array<string, string>
     */
    public function getThemeCssVariablesAttribute(): array
    {
        return ThemePalette::cssVariables(
            $this->effective_primary_color,
            $this->effective_secondary_color,
        );
    }

    public function getSocialLinksAttribute(): array
    {
        $links = [
            [
                'label' => 'Twitter/X',
                'handle' => $this->x_handle,
                'url' => $this->normalizeSocialUrl($this->x_handle, 'https://x.com/'),
                'path' => 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z',
            ],
            [
                'label' => 'Instagram',
                'handle' => $this->instagram_handle,
                'url' => $this->normalizeSocialUrl($this->instagram_handle, 'https://www.instagram.com/'),
                'path' => 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z',
            ],
            [
                'label' => 'Facebook',
                'handle' => $this->facebook_handle,
                'url' => $this->normalizeSocialUrl($this->facebook_handle, 'https://www.facebook.com/'),
                'path' => 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
            ],
            [
                'label' => 'TikTok',
                'handle' => $this->tiktok_handle,
                'url' => $this->normalizeSocialUrl($this->tiktok_handle, 'https://www.tiktok.com/@', prefixAt: true),
                'path' => 'M19.589 6.686a4.793 4.793 0 01-3.77-4.235V2h-3.193v13.402a2.039 2.039 0 01-2.039 2.039 2.039 2.039 0 010-4.078c.167 0 .328.022.482.06V10.17a5.266 5.266 0 00-.482-.023A5.232 5.232 0 005.355 15.38a5.232 5.232 0 005.232 5.232 5.232 5.232 0 005.232-5.232V9.693a8.003 8.003 0 004.181 1.175V7.675a4.77 4.77 0 01-.411-.989z',
            ],
            [
                'label' => 'YouTube',
                'handle' => $this->youtube_handle,
                'url' => $this->normalizeSocialUrl($this->youtube_handle, 'https://www.youtube.com/@', prefixAt: true),
                'path' => 'M23.498 6.186a2.997 2.997 0 00-2.11-2.12C19.536 3.5 12 3.5 12 3.5s-7.536 0-9.388.566a2.997 2.997 0 00-2.11 2.12C0 8.05 0 12 0 12s0 3.95.502 5.814a2.997 2.997 0 002.11 2.12C4.464 20.5 12 20.5 12 20.5s7.536 0 9.388-.566a2.997 2.997 0 002.11-2.12C24 15.95 24 12 24 12s0-3.95-.502-5.814zM9.75 15.568V8.432L15.818 12 9.75 15.568z',
            ],
        ];

        return array_values(array_filter($links, fn (array $link): bool => filled($link['url'])));
    }

    protected function normalizeSocialUrl(?string $value, string $baseUrl, bool $prefixAt = false): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $value = trim($value);

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        $value = ltrim($value, '@/');

        if (! filled($value)) {
            return null;
        }

        if ($prefixAt) {
            return $baseUrl . $value;
        }

        return $baseUrl . $value;
    }

    protected static function flushRuntimeCaches(): bool
    {
        static::$supportsDiscoveryVisibilityCache = null;
        static::$supportsFeaturedAlbumsCache = null;
        static::$supportsDiscoveryOrderingCache = null;
        static::$supportsUploadLimitsCache = null;
        static::$supportsMonetizationControlsCache = null;
        static::$supportsCurrencyControlsCache = null;
        static::$platformCurrencyCache = null;
        static::$supportsPaymentFeeControlCache = null;
        static::$platformFeePercentCache = null;
        static::$supportsThemeCustomizationCache = null;
        static::$supportsPublicThemeToggleControlCache = null;
        static::$supportsAutoPlaylistControlsCache = null;
        static::$supportsAutoQueueLimitsCache = null;
        static::$supportsRegistrationRoleGuideControlCache = null;
        static::$supportsRegistrationVerificationControlCache = null;
        static::$paymentsEnabledCache = null;
        static::$autoPlaylistEnabledCache = null;
        static::$autoQueueTotalLimitCache = null;
        static::$autoQueueAlbumLimitCache = null;
        static::$autoQueueArtistLimitCache = null;
        static::$autoQueueGenreLimitCache = null;
        static::$registrationRequiresEmailVerificationCache = null;

        return true;
    }
}
