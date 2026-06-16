<?php

namespace App\Models;

use App\Support\CurrencyHelper;
use App\Support\UploadLimits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ArtistProfile extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, Searchable, SoftDeletes;

    public const FEATURE_RECOMMENDATION_MIN_PUBLISHED_TRACKS = 3;

    public const FEATURE_RECOMMENDATION_MIN_TOTAL_PLAYS = 500;

    public const FEATURE_RECOMMENDATION_MIN_FOLLOWERS = 25;

    public const FEATURE_RECOMMENDATION_RECENT_DAYS = 60;

    public const FEATURE_RECOMMENDATION_MIN_SCORE = 60;

    protected static ?bool $supportsFeaturedCurationCache = null;

    protected $fillable = [
        'user_id',
        'stage_name',
        'slug',
        'bio',
        'website',
        'twitter',
        'instagram',
        'youtube',
        'spotify',
        'is_verified',
        'is_approved',
        'is_active',
        'is_featured',
        'preferred_currency',
        'admin_currency_override',
        'country_code',
        'stripe_connect_account_id',
        'stripe_connect_status',
    ];

    protected $casts = [
        'is_verified'   => 'boolean',
        'is_approved'   => 'boolean',
        'is_active'     => 'boolean',
        'is_featured'   => 'boolean',
        'total_plays'   => 'integer',
        'followers_count' => 'integer',
        'total_donations' => 'decimal:2',
        'preferred_currency' => 'string',
        'admin_currency_override' => 'string',
        'country_code' => 'string',
        'stripe_connect_status' => 'string',
    ];

    protected static function booted(): void
    {
        static::creating(function (ArtistProfile $profile) {
            if (empty($profile->slug)) {
                $profile->slug = Str::slug($profile->stage_name);
            }
        });

        static::created(function (ArtistProfile $profile): void {
            $profile->loadMissing('user');
            $profile->user?->syncArtistVerificationState();
        });
    }

    // ─── Media Collections ────────────────────────────────────────────────────
    public function registerMediaCollections(): void
    {
        $disk = config('media-storage.public_disk', config('media-library.disk_name'));

        $this->addMediaCollection('avatar')
            ->useDisk($disk)
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->acceptsFile(fn (File $file) => $file->size <= UploadLimits::imageBytes())
            ->withResponsiveImages();

        $this->addMediaCollection('banner')
            ->useDisk($disk)
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->sharpen(10)
            ->format('webp')
            ->performOnCollections('avatar');

        $this->addMediaConversion('preview')
            ->width(600)
            ->height(400)
            ->format('webp')
            ->performOnCollections('banner');

        $this->addMediaConversion('share')
            ->fit(Fit::Contain, 1200, 1200, false, '#ffffff')
            ->format('jpg')
            ->quality(85)
            ->performOnCollections('avatar')
            ->nonQueued();
    }

    // ─── Relationships ─────────────────────────────────────────────────────────
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function genres(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'artist_genre');
    }

    public function tracks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Track::class);
    }

    public function albums(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Album::class);
    }

    public function followers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Follow::class);
    }

    public function donations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Donation::class);
    }

    // ─── Scout ────────────────────────────────────────────────────────────────
    public function toSearchableArray(): array
    {
        return [
            'id'         => $this->id,
            'stage_name' => $this->stage_name,
            'bio'        => $this->bio,
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    public function getAvatarMediaUrl(string $conversion = 'thumb'): string
    {
        $avatar = $this->getFirstMedia('avatar');

        if ($avatar) {
            return $conversion !== ''
                ? $avatar->getAvailableUrl([$conversion])
                : $avatar->getUrl();
        }

        $user = $this->relationLoaded('user') ? $this->user : $this->user()->first();

        return $user?->getAvatarMediaUrl($conversion) ?: SiteSetting::defaultImageUrl();
    }

    public function getAvatarUrl(string $conversion = 'thumb'): string
    {
        return $this->getAvatarMediaUrl($conversion)
            ?: 'https://ui-avatars.com/api/?name=' . urlencode($this->display_name) . '&background=ef4444&color=fff&size=200';
    }

    public function getBannerUrl(string $conversion = 'preview'): string
    {
        $banner = $this->getFirstMedia('banner');

        if (! $banner) {
            return '';
        }

        return $conversion !== ''
            ? $banner->getAvailableUrl([$conversion])
            : $banner->getUrl();
    }

    public function getShareImageUrl(): string
    {
        return $this->getAvatarMediaUrl('share');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->stage_name ?: ($this->user?->name ?? 'Artist');
    }

    public function getAvatarAltAttribute(): string
    {
        return "{$this->display_name} profile image";
    }

    public function getBannerAltAttribute(): string
    {
        return "{$this->display_name} artist banner";
    }

    public function getWebsiteUrlAttribute(): ?string
    {
        return $this->normalizeExternalUrl($this->website);
    }

    public function getInstagramUrlAttribute(): ?string
    {
        return $this->normalizeProfileUrl($this->instagram, 'https://instagram.com/');
    }

    public function getTwitterUrlAttribute(): ?string
    {
        return $this->normalizeProfileUrl($this->twitter, 'https://twitter.com/');
    }

    public function getYoutubeUrlAttribute(): ?string
    {
        return $this->normalizeProfileUrl($this->youtube, 'https://youtube.com/');
    }

    public function getSpotifyUrlAttribute(): ?string
    {
        return $this->normalizeProfileUrl($this->spotify, 'https://open.spotify.com/');
    }

    public static function supportsFeaturedCuration(): bool
    {
        if (static::$supportsFeaturedCurationCache !== null) {
            return static::$supportsFeaturedCurationCache;
        }

        return static::$supportsFeaturedCurationCache = Schema::hasColumn('artist_profiles', 'is_featured');
    }

    // ─── Currency & Payouts ───────────────────────────────────────────────────

    /**
     * Resolve the currency this artist should receive donations in.
     * Priority: admin override → artist preference → platform default (NGN).
     */
    public function effectiveCurrency(): string
    {
        $candidate = $this->admin_currency_override ?? $this->preferred_currency;

        if (filled($candidate) && CurrencyHelper::isSupported($candidate)) {
            return CurrencyHelper::normalize($candidate);
        }

        return SiteSetting::platformCurrency();
    }

    public function hasStripeConnect(): bool
    {
        return $this->stripe_connect_status === 'active'
            && filled($this->stripe_connect_account_id);
    }

    public function stripeConnectPending(): bool
    {
        return $this->stripe_connect_status === 'pending';
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true)->where('is_active', true);
    }

    public function scopePubliclyDiscoverable($query)
    {
        return $query
            ->approved()
            ->whereHas('tracks', fn ($trackQuery) => $trackQuery->published());
    }

    public function scopeInLimbo($query)
    {
        return $query
            ->approved()
            ->whereDoesntHave('tracks', fn ($trackQuery) => $trackQuery->published());
    }

    public function scopeWithFeaturedRecommendationMetrics($query)
    {
        return $query
            ->withCount([
                'tracks as published_tracks_count' => fn (Builder $trackQuery): Builder => $trackQuery->published(),
                'tracks as recent_published_tracks_count' => fn (Builder $trackQuery): Builder => $trackQuery
                    ->published()
                    ->where('created_at', '>=', now()->subDays(self::FEATURE_RECOMMENDATION_RECENT_DAYS)),
            ])
            ->withSum([
                'tracks as published_tracks_downloads_sum' => fn (Builder $trackQuery): Builder => $trackQuery->published(),
            ], 'downloads_count');
    }

    public function scopeFeaturedRecommendationEligible($query)
    {
        return $query
            ->publiclyDiscoverable()
            ->whereHas('tracks', fn (Builder $trackQuery): Builder => $trackQuery->published(), '>=', self::FEATURE_RECOMMENDATION_MIN_PUBLISHED_TRACKS)
            ->where(function (Builder $query): void {
                $query
                    ->where('total_plays', '>=', self::FEATURE_RECOMMENDATION_MIN_TOTAL_PLAYS)
                    ->orWhere('followers_count', '>=', self::FEATURE_RECOMMENDATION_MIN_FOLLOWERS);
            });
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeFeatured($query)
    {
        if (!static::supportsFeaturedCuration()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('is_featured', true);
    }

    public function featuredRecommendationScore(): int
    {
        if (! $this->isFeaturedRecommendationEligible()) {
            return 0;
        }

        $score = 0;
        $score += min(35, (int) floor(($this->total_plays ?? 0) / 200));
        $score += min(25, (int) floor(($this->followers_count ?? 0) / 5));
        $score += min(15, (int) floor($this->publishedTracksDownloadsSum() / 5));
        $score += min(15, $this->publishedTracksCount() * 4);
        $score += $this->recentPublishedTracksCount() > 0 ? 5 : 0;
        $score += ($this->is_verified ? 3 : 0);
        $score += ($this->total_donations ?? 0) > 0 ? 2 : 0;

        return min(100, $score);
    }

    public function isFeaturedRecommendationEligible(): bool
    {
        return $this->isDiscoverableForFeaturedRecommendation()
            && $this->publishedTracksCount() >= self::FEATURE_RECOMMENDATION_MIN_PUBLISHED_TRACKS
            && (($this->total_plays ?? 0) >= self::FEATURE_RECOMMENDATION_MIN_TOTAL_PLAYS
                || ($this->followers_count ?? 0) >= self::FEATURE_RECOMMENDATION_MIN_FOLLOWERS);
    }

    public function isFeaturedRecommended(): bool
    {
        return ! $this->is_featured
            && $this->featuredRecommendationScore() >= self::FEATURE_RECOMMENDATION_MIN_SCORE;
    }

    public function getFeaturedRecommendationScoreAttribute(): int
    {
        return $this->featuredRecommendationScore();
    }

    public function getIsFeaturedRecommendationEligibleAttribute(): bool
    {
        return $this->isFeaturedRecommendationEligible();
    }

    public function getIsFeaturedRecommendedAttribute(): bool
    {
        return $this->isFeaturedRecommended();
    }

    public function getFeaturedRecommendationStatusAttribute(): string
    {
        if ($this->is_featured) {
            return 'Featured';
        }

        if ($this->isFeaturedRecommended()) {
            return 'Recommended';
        }

        if ($this->isFeaturedRecommendationEligible()) {
            return 'Eligible';
        }

        return 'Not eligible';
    }

    public static function rankForFeaturedListing(Collection $profiles): Collection
    {
        return $profiles
            ->sort(function (ArtistProfile $left, ArtistProfile $right): int {
                return (($right->is_featured ? 1 : 0) <=> ($left->is_featured ? 1 : 0))
                    ?: (($right->isFeaturedRecommended() ? 1 : 0) <=> ($left->isFeaturedRecommended() ? 1 : 0))
                    ?: ($right->featuredRecommendationScore() <=> $left->featuredRecommendationScore())
                    ?: (($right->is_verified ? 1 : 0) <=> ($left->is_verified ? 1 : 0))
                    ?: (($right->followers_count ?? 0) <=> ($left->followers_count ?? 0))
                    ?: (($right->total_plays ?? 0) <=> ($left->total_plays ?? 0));
            })
            ->values();
    }

    public function markVerifiedAndApproved(): void
    {
        $this->forceFill([
            'is_verified' => true,
            'is_approved' => true,
            'is_active' => true,
        ])->save();
    }

    public function markPendingEmailVerification(): void
    {
        $this->forceFill([
            'is_verified' => false,
            'is_approved' => false,
            'is_active' => true,
        ])->save();
    }

    public function rejectFromModeration(): void
    {
        $this->forceFill([
            'is_approved' => false,
            'is_active' => true,
        ])->save();
    }

    protected function isDiscoverableForFeaturedRecommendation(): bool
    {
        return (bool) ($this->is_approved && $this->is_active && $this->publishedTracksCount() > 0);
    }

    protected function publishedTracksCount(): int
    {
        return (int) ($this->published_tracks_count ?? 0);
    }

    protected function recentPublishedTracksCount(): int
    {
        return (int) ($this->recent_published_tracks_count ?? 0);
    }

    protected function publishedTracksDownloadsSum(): int
    {
        return (int) ($this->published_tracks_downloads_sum ?? 0);
    }

    protected function normalizeExternalUrl(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        return 'https://' . ltrim($value, '/');
    }

    protected function normalizeProfileUrl(?string $value, string $baseUrl): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($value, '@/');
    }
}
