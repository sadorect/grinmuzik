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
    use Concerns\SchedulesRelease;

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

        static::updated(function (Album $album) {
            $album->syncTracksScheduledWithAlbum();
        });
    }

    /**
     * Hold every track of this release that is not live yet until the album's
     * scheduled time, so they all go live together.
     */
    public function scheduleUnreleasedTracks(): void
    {
        if (! $this->is_scheduled) {
            return;
        }

        $this->tracks()->where('is_published', false)->get()
            ->each(fn (Track $track) => $track->update(['scheduled_release_at' => $this->scheduled_release_at]));
    }

    /**
     * Tracks scheduled for exactly the album's release time travel with it:
     * released together, rescheduled together, or put back to draft together.
     * This holds however the album changed (artist form, admin panel, scheduler).
     */
    protected function syncTracksScheduledWithAlbum(): void
    {
        $previous = $this->getOriginal('scheduled_release_at');

        if (! $previous || ! $this->wasChanged('scheduled_release_at')) {
            return;
        }

        $tied = $this->tracks()->scheduled()->where('scheduled_release_at', $previous)->get();

        foreach ($tied as $track) {
            if ($this->is_published) {
                // Due release: date the tracks to the album's time, not "now".
                $track->releaseNow($previous->isPast() ? $previous : null);
            } else {
                $track->update(['scheduled_release_at' => $this->scheduled_release_at]);
            }
        }
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

    /** Cover art big enough for a TV screen; see Track::getLargeCoverUrl(). */
    public function getLargeCoverUrl(): string
    {
        $media = $this->getFirstMedia('cover');

        if (! $media) {
            return SiteSetting::defaultImageUrl();
        }

        return $media->getAvailableUrl(['large', 'share', 'thumb']);
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
        if ($this->is_scheduled) {
            return 'Scheduled';
        }

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
