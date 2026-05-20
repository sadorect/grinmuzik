<?php

namespace App\Models;

use App\Support\UploadLimits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Album extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, Searchable;

    public const PUBLIC_TYPES = ['album', 'ep'];

    protected $fillable = [
        'user_id',
        'artist_profile_id',
        'title',
        'slug',
        'description',
        'release_date',
        'genre_id',
        'type',
        'is_published',
        'play_count',
    ];

    protected $casts = [
        'release_date' => 'date',
        'is_published' => 'boolean',
        'play_count'   => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Album $album) {
            if (empty($album->slug)) {
                $album->slug = static::generateUniqueSlug($album->title);
            }
        });
    }

    // ─── Media Collections ────────────────────────────────────────────────────
    public function registerMediaCollections(): void
    {
        $disk = config('media-storage.public_disk', config('media-library.disk_name'));

        $this->addMediaCollection('cover')
            ->useDisk($disk)
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->acceptsFile(fn (File $file) => $file->size <= UploadLimits::imageBytes())
            ->withResponsiveImages();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->format('webp')
            ->performOnCollections('cover');

        $this->addMediaConversion('large')
            ->width(800)
            ->height(800)
            ->format('webp')
            ->performOnCollections('cover');

        $this->addMediaConversion('share')
            ->fit(Fit::Contain, 1200, 1200, false, '#ffffff')
            ->format('jpg')
            ->quality(85)
            ->performOnCollections('cover')
            ->nonQueued();
    }

    // ─── Relationships ─────────────────────────────────────────────────────────
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function artist(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ArtistProfile::class, 'artist_profile_id');
    }

    public function genre(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Genre::class);
    }

    public function tracks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Track::class)->orderBy('track_number');
    }

    public function publicTracks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->tracks()->where('is_published', true);
    }

    // ─── Scout ────────────────────────────────────────────────────────────────
    public function toSearchableArray(): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    public function getCoverUrl(string $conversion = 'thumb'): string
    {
        return $this->getFirstMediaUrl('cover', $conversion)
            ?: SiteSetting::defaultImageUrl();
    }

    public function getShareImageUrl(): string
    {
        return $this->getFirstMediaUrl('cover', 'share') ?: SiteSetting::defaultImageUrl();
    }

    public function getCoverAltAttribute(): string
    {
        $artistName = $this->artist?->stage_name
            ?? $this->artist?->user?->name
            ?? 'Unknown artist';

        return "{$this->title} album cover by {$artistName}";
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getIsUpcomingAttribute(): bool
    {
        return $this->release_date?->isFuture() ?? false;
    }

    public function getReleaseStatusLabelAttribute(): string
    {
        if (! $this->is_published) {
            return 'Draft';
        }

        if ($this->is_upcoming) {
            return 'Upcoming';
        }

        return 'Live';
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopePubliclyVisible($query)
    {
        return $query
            ->published()
            ->whereIn('type', self::PUBLIC_TYPES);
    }

    public function scopeWithPublicTrackCount($query)
    {
        return $query->withCount(['publicTracks as public_tracks_count']);
    }

    protected static function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'album';
        $slug = $base;
        $suffix = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
