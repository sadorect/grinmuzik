<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One recorded action. Append-only: see AuditLogger for the write path.
 */
class ActivityLog extends Model
{
    // Rows are never edited, so an updated_at column would always equal
    // created_at. The migration only creates the one timestamp.
    public const UPDATED_AT = null;

    protected $fillable = [
        'event',
        'actor_id',
        'actor_type',
        'actor_label',
        'subject_type',
        'subject_id',
        'subject_label',
        'artist_profile_id',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Every event key the app writes, grouped for the admin filter.
     *
     * Kept as data rather than pulled with a DISTINCT so the filter lists
     * events that simply have not happened yet — an empty "track.restored"
     * option is more informative than a missing one.
     *
     * @return array<string, string>
     */
    public static function eventLabels(): array
    {
        return [
            'track.uploaded' => 'Track uploaded',
            'track.updated' => 'Track edited',
            'track.published' => 'Track published',
            'track.unpublished' => 'Track set to draft',
            'track.deleted' => 'Track deleted',
            'track.restored' => 'Track restored',
            'track.force_deleted' => 'Track permanently deleted',

            'album.created' => 'Album created',
            'album.updated' => 'Album edited',
            'album.deleted' => 'Album deleted',

            'artist.created' => 'Artist profile created',
            'artist.updated' => 'Artist profile edited',
            'artist.verified' => 'Artist verified',
            'artist.unverified' => 'Artist verification removed',
            'artist.approved' => 'Artist approved',
            'artist.unapproved' => 'Artist approval removed',
            'artist.activated' => 'Artist reactivated',
            'artist.deactivated' => 'Artist deactivated',
            'artist.featured' => 'Artist featured',
            'artist.unfeatured' => 'Artist unfeatured',
            'artist.warned' => 'Artist warned',
            'artist.deleted' => 'Artist profile deleted',
            'artist.restored' => 'Artist profile restored',

            'auth.login' => 'Signed in',
        ];
    }

    public static function actorTypeLabels(): array
    {
        return [
            'artist' => 'Artist',
            'admin' => 'Admin',
            'listener' => 'Listener',
            'system' => 'System',
        ];
    }

    public function eventLabel(): string
    {
        return static::eventLabels()[$this->event] ?? $this->event;
    }

    /**
     * What to show when the actor's user row has been deleted.
     */
    public function actorName(): string
    {
        return $this->actor?->name
            ?? $this->actor_label
            ?? 'System';
    }

    public function subjectName(): string
    {
        return $this->subject_label ?? '—';
    }

    /**
     * Changed attributes as a flat list, for rendering a diff.
     *
     * @return array<int, array{field: string, old: mixed, new: mixed}>
     */
    public function changes(): array
    {
        $changes = [];

        foreach ($this->properties ?? [] as $field => $value) {
            if (! is_array($value) || ! array_key_exists('new', $value)) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'old' => $value['old'] ?? null,
                'new' => $value['new'],
            ];
        }

        return $changes;
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function artistProfile(): BelongsTo
    {
        return $this->belongsTo(ArtistProfile::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForArtist(Builder $query, int $artistProfileId): Builder
    {
        return $query->where('artist_profile_id', $artistProfileId);
    }

    public function scopeEvent(Builder $query, string|array $event): Builder
    {
        return $query->whereIn('event', (array) $event);
    }

    public function scopeDestructive(Builder $query): Builder
    {
        return $query->whereIn('event', [
            'track.deleted',
            'track.force_deleted',
            'album.deleted',
            'artist.deleted',
            'artist.deactivated',
        ]);
    }
}
