<?php

namespace App\Models;

use App\Support\ArtistActivityNotifier;
use App\Support\UploadLimits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class Track extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, Searchable, SoftDeletes;

    public static function moodDefinitions(): array
    {
        return collect(Mood::definitions())
            ->map(fn (array $definition) => [
                'label' => $definition['name'],
                'genres' => $definition['related_genres'] ?? [],
                'moods' => $definition['keywords'] ?? [],
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function moodOptions(): array
    {
        return Mood::optionMap();
    }

    public static function suggestedMoodSlugForGenre(?string $genreSlug): ?string
    {
        return Mood::suggestedSlugForGenre($genreSlug);
    }

    protected $fillable = [
        'user_id',
        'artist_profile_id',
        'album_id',
        'genre_id',
        'title',
        'slug',
        'description',
        'lyrics',
        'mood',
        'duration',
        'audio_file_path',
        'waveform_data',
        'is_published',
        'is_featured',
        'is_free',
        'requires_donation',
        'donation_amount',
        'allow_artist_gifts',
        'play_count',
        'downloads_count',
        'track_number',
    ];

    protected $casts = [
        'is_published'    => 'boolean',
        'is_featured'     => 'boolean',
        'is_free'         => 'boolean',
        'allow_artist_gifts' => 'boolean',
        'donation_amount' => 'decimal:2',
        'play_count'      => 'integer',
        'downloads_count' => 'integer',
        'track_number'    => 'integer',
        'duration'        => 'integer',
        'waveform_data'   => 'array',
    ];

    /**
     * Backward-compatible accessor: requires_donation === !is_free
     */
    public function getRequiresDonationAttribute(): bool
    {
        if (! SiteSetting::paymentsEnabled()) {
            return false;
        }

        return ! ($this->is_free ?? true);
    }

    public function setRequiresDonationAttribute(bool $value): void
    {
        $this->attributes['is_free'] = !$value;
    }

    /**
     * Backward-compatible accessor: is_demo === is_featured
     */
    public function getIsDemoAttribute(): bool
    {
        return (bool) ($this->is_featured ?? false);
    }

    public function setIsDemoAttribute(bool $value): void
    {
        $this->attributes['is_featured'] = $value;
    }

    protected static function booted(): void
    {
        static::creating(function (Track $track) {
            if (empty($track->slug)) {
                $track->slug = Str::slug($track->title);
            }
        });
    }

    // ─── Media Collections ────────────────────────────────────────────────────
    public function registerMediaCollections(): void
    {
        $publicDisk = config('media-storage.public_disk', config('media-library.disk_name'));
        $privateDisk = config('media-storage.private_disk', 'local');

        $this->addMediaCollection('audio')
            ->useDisk($privateDisk)
            ->singleFile()
            ->acceptsMimeTypes(['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/flac', 'audio/ogg', 'audio/aac', 'audio/mp4', 'audio/x-m4a', 'video/mp4'])
            ->acceptsFile(fn (File $file) => $file->size <= UploadLimits::audioBytes());

        $this->addMediaCollection('cover')
            ->useDisk($publicDisk)
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

    public function artistProfile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ArtistProfile::class, 'artist_profile_id');
    }

    /** @deprecated Use artistProfile() */
    public function artist(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->artistProfile();
    }

    public function album(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function genre(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Genre::class);
    }

    public function playlists(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'playlist_track')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function likedByUsers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'likes')
            ->withTimestamps();
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->orderBy('created_at', 'desc');
    }

    public function allComments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function playHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PlayHistory::class);
    }

    public function donations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Donation::class);
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

    // ─── Accessors ────────────────────────────────────────────────────────────
    public function getDurationInSecondsAttribute(): int
    {
        return self::normalizeDurationValue($this->getRawOriginal('duration') ?? $this->duration);
    }

    public function getFormattedDurationAttribute(): string
    {
        $duration = $this->duration_in_seconds;

        if ($duration < 1) {
            return '0:00';
        }

        $hours = intdiv($duration, 3600);
        $minutes = intdiv($duration % 3600, 60);
        $seconds = $duration % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    protected static function normalizeDurationValue(mixed $value): int
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return max(0, (int) round((float) $value));
        }

        if (! is_string($value)) {
            return 0;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || ! str_contains($trimmed, ':')) {
            return 0;
        }

        $segments = array_map('trim', explode(':', $trimmed));

        if (count($segments) < 2 || count($segments) > 3) {
            return 0;
        }

        if (collect($segments)->contains(fn ($segment) => $segment === '' || ! ctype_digit($segment))) {
            return 0;
        }

        $segments = array_map('intval', $segments);

        if (count($segments) === 2) {
            [$minutes, $seconds] = $segments;

            return ($minutes * 60) + $seconds;
        }

        [$hours, $minutes, $seconds] = $segments;

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    public function getCoverUrl(string $conversion = 'thumb'): string
    {
        return $this->getFirstMediaUrl('cover', $conversion)
            ?: ($this->album?->getCoverUrl($conversion) ?: SiteSetting::defaultImageUrl());
    }

    public function getShareImageUrl(): string
    {
        return $this->getFirstMediaUrl('cover', 'share')
            ?: ($this->album?->getShareImageUrl() ?: SiteSetting::defaultImageUrl());
    }

    public function getCoverAltAttribute(): string
    {
        $artistName = $this->artistProfile?->stage_name
            ?? $this->artistProfile?->user?->name
            ?? 'Unknown artist';

        return "{$this->title} cover art by {$artistName}";
    }

    public function getEffectiveMoodAttribute(): ?string
    {
        if ($this->mood) {
            return Str::of($this->mood)->lower()->replace('_', ' ')->replace('-', ' ')->trim()->value();
        }

        return self::suggestedMoodSlugForGenre($this->genre?->slug);
    }

    public function getMoodLabelAttribute(): ?string
    {
        $effectiveMood = $this->effective_mood;

        if (!$effectiveMood) {
            return null;
        }

        $label = self::moodOptions()[$effectiveMood] ?? null;

        if ($label) {
            return $label;
        }

        return Str::of($effectiveMood)->replace('_', ' ')->replace('-', ' ')->title()->value();
    }

    public function getAudioUrl(): string
    {
        $privateDisk = config('media-storage.private_disk', 'local');
        $audio = $this->getFirstMedia('audio');

        if ($audio) {
            if ($audio->disk === $privateDisk) {
                try {
                    return $audio->getTemporaryUrl(
                        now()->addMinutes((int) config('media-storage.private_url_ttl_minutes', 15))
                    );
                } catch (Throwable $exception) {
                    report($exception);

                    return '';
                }
            }

            return $audio->getUrl();
        }

        if ($this->audio_file_path) {
            $candidateDisks = array_values(array_unique(array_filter([
                $privateDisk,
                config('media-storage.public_disk', config('media-library.disk_name')),
                'public',
            ])));

            foreach ($candidateDisks as $disk) {
                try {
                    $storage = Storage::disk($disk);

                    if (! $storage->exists($this->audio_file_path)) {
                        continue;
                    }

                    return $disk === $privateDisk
                        ? $storage->temporaryUrl(
                            $this->audio_file_path,
                            now()->addMinutes((int) config('media-storage.private_url_ttl_minutes', 15))
                        )
                        : $storage->url($this->audio_file_path);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }

        return '';
    }

    public function getAudioDownloadSource(): ?array
    {
        $audio = $this->getFirstMedia('audio');

        if ($audio) {
            return [
                'disk' => $audio->disk,
                'path' => $audio->getPathRelativeToRoot(),
            ];
        }

        if ($this->audio_file_path) {
            $candidateDisks = array_values(array_unique(array_filter([
                config('media-storage.private_disk', 'local'),
                config('media-storage.public_disk', config('media-library.disk_name')),
                'public',
            ])));

            foreach ($candidateDisks as $disk) {
                try {
                    if (Storage::disk($disk)->exists($this->audio_file_path)) {
                        return [
                            'disk' => $disk,
                            'path' => $this->audio_file_path,
                        ];
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }

        return null;
    }

    public function getDownloadFilename(): string
    {
        $extension = null;
        $audio = $this->getFirstMedia('audio');

        if ($audio) {
            $extension = pathinfo($audio->file_name, PATHINFO_EXTENSION);
        } elseif ($this->audio_file_path) {
            $extension = pathinfo($this->audio_file_path, PATHINFO_EXTENSION);
        }

        $extension = Str::of($extension ?: 'mp3')->lower()->value();
        $baseName = Str::slug($this->title) ?: 'track-' . $this->getKey();

        return "{$baseName}.{$extension}";
    }

    public function isUnlockedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (! $this->requires_donation) {
            return true;
        }

        return Donation::query()
            ->where('user_id', $user->id)
            ->where('track_id', $this->id)
            ->unlocks()
            ->completed()
            ->exists();
    }

    public function canBeDownloadedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return ! $this->requires_donation || $this->isUnlockedBy($user);
    }

    public function canReceiveGifts(): bool
    {
        return SiteSetting::paymentsEnabled()
            && filled($this->artist_profile_id);
    }

    public function suggestMood(): ?string
    {
        return self::suggestedMoodSlugForGenre($this->genre?->slug);
    }

    public function fillSuggestedMood(bool $force = false): bool
    {
        if (! $force && filled($this->mood)) {
            return false;
        }

        $suggestedMood = $this->suggestMood();

        if (! $suggestedMood || $this->mood === $suggestedMood) {
            return false;
        }

        $this->mood = $suggestedMood;

        return true;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeDemo($query)
    {
        return $query->where('is_featured', true);
    }

    public function incrementPlayCount(): void
    {
        $this->increment('play_count');
        $this->artist?->increment('total_plays');
        $this->refresh();

        app(ArtistActivityNotifier::class)->sendTrackPlayMilestoneIfReached($this);
    }

    public function incrementDownloadsCount(): void
    {
        $this->increment('downloads_count');
        $this->refresh();

        app(ArtistActivityNotifier::class)->sendTrackDownloadMilestoneIfReached($this);
    }
}
