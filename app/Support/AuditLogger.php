<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Write path for the audit trail.
 *
 * Every method here is best-effort: a failure to record an action must never
 * be allowed to fail the action itself. An artist should not be blocked from
 * deleting their own track because the log table is full or locked, so the
 * whole write is wrapped and reported rather than thrown.
 */
class AuditLogger
{
    /**
     * Attributes that change on their own and would bury real edits.
     *
     * Counters tick on every play, like and follow; a track edit would
     * otherwise arrive alongside a dozen play_count rows. remember_token and
     * the timestamps are noise for the same reason.
     */
    private const IGNORED_ATTRIBUTES = [
        'updated_at',
        'created_at',
        // Soft-delete state is already reported as its own deleted/restored
        // event; including it here would log every removal twice.
        'deleted_at',
        'play_count',
        'likes_count',
        'downloads_count',
        'total_plays',
        'followers_count',
        'total_donations',
        'last_seen_at',
        'remember_token',
        'password',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
        'waveform_data',
    ];

    /**
     * Record an action.
     *
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $event,
        ?Model $subject = null,
        array $properties = [],
        ?string $subjectLabel = null,
        ?int $artistProfileId = null,
    ): ?ActivityLog {
        try {
            $actor = $this->resolveActor();
            $properties = $this->withImpersonator($event, $properties);

            return ActivityLog::create([
                'event' => $event,
                'actor_id' => $actor?->getKey(),
                'actor_type' => $this->resolveActorType($actor),
                'actor_label' => $actor?->name,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subjectLabel ?? $this->resolveSubjectLabel($subject),
                'artist_profile_id' => $artistProfileId ?? $this->resolveArtistProfileId($subject, $actor),
                'properties' => $properties === [] ? null : $properties,
                'ip_address' => $this->requestValue(fn () => request()->ip()),
                'user_agent' => $this->requestValue(fn () => request()->userAgent()),
            ]);
        } catch (Throwable $exception) {
            // Never let auditing break the thing being audited.
            report($exception);

            return null;
        }
    }

    /**
     * Record a model update, diffing only the attributes that actually changed.
     *
     * Returns null when nothing meaningful changed, so a play-count tick does
     * not produce an empty "edited" row.
     */
    public function recordChanges(string $event, Model $subject, ?string $subjectLabel = null): ?ActivityLog
    {
        $changes = $this->diff($subject);

        if ($changes === []) {
            return null;
        }

        return $this->record($event, $subject, $changes, $subjectLabel);
    }

    /**
     * Build a {field: {old, new}} diff of a model's dirty attributes.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function diff(Model $subject): array
    {
        $changes = [];

        foreach ($subject->getChanges() as $attribute => $new) {
            if (in_array($attribute, self::IGNORED_ATTRIBUTES, true)) {
                continue;
            }

            $old = $subject->getOriginal($attribute);

            // getChanges() can report a change when only the type differs
            // (a "1" becoming 1 after a cast), which is not worth a log row.
            if ($old == $new && gettype($old) !== gettype($new)) {
                continue;
            }

            $changes[$attribute] = [
                'old' => $this->normalise($old),
                'new' => $this->normalise($new),
            ];
        }

        return $changes;
    }

    /**
     * Whether a given attribute is one the log cares about.
     */
    public function tracks(string $attribute): bool
    {
        return ! in_array($attribute, self::IGNORED_ATTRIBUTES, true);
    }

    /**
     * While the super admin is signed in as someone, every action is the
     * admin's, not the account owner's. Say so on the row.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function withImpersonator(string $event, array $properties): array
    {
        if (str_starts_with($event, 'impersonation.')) {
            return $properties;
        }

        try {
            $impersonator = app(Impersonation::class)->impersonator();
        } catch (Throwable) {
            return $properties;
        }

        if (! $impersonator) {
            return $properties;
        }

        return $properties + [
            'impersonated_by' => [
                'id' => $impersonator->getKey(),
                'name' => $impersonator->name,
            ],
        ];
    }

    private function resolveActor(): ?User
    {
        // auth() throws outside a configured container (some console paths),
        // and returns null for scheduled work — both mean "system".
        try {
            $user = auth()->user();
        } catch (Throwable) {
            return null;
        }

        return $user instanceof User ? $user : null;
    }

    /**
     * How the actor was acting, frozen at write time.
     *
     * Admin wins over artist: an admin who also owns an artist profile and
     * deletes someone else's track was acting as an admin.
     */
    private function resolveActorType(?User $actor): string
    {
        if (! $actor) {
            return 'system';
        }

        try {
            return match (true) {
                $actor->isAdmin() => 'admin',
                $actor->isArtist() => 'artist',
                default => 'listener',
            };
        } catch (Throwable) {
            return 'listener';
        }
    }

    /**
     * A human-readable name for the subject, frozen so the row stays
     * readable once the subject is gone.
     */
    private function resolveSubjectLabel(?Model $subject): ?string
    {
        if (! $subject) {
            return null;
        }

        foreach (['title', 'stage_name', 'name'] as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * Which artist the action concerns.
     *
     * Falls back to the actor's own profile so artist-side events with no
     * obvious owning relation (a login) still group under that artist.
     */
    private function resolveArtistProfileId(?Model $subject, ?User $actor): ?int
    {
        $ownerId = $subject?->getAttribute('artist_profile_id');

        if (is_numeric($ownerId)) {
            return (int) $ownerId;
        }

        // An ArtistProfile is its own owner.
        if ($subject instanceof \App\Models\ArtistProfile) {
            return $subject->getKey();
        }

        try {
            return $actor?->artistProfile()->value('id');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Keep properties JSON-safe and readable.
     */
    private function normalise(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : null;
        }

        // Long text (a bio, lyrics) makes the log unreadable and the table fat;
        // the fact that it changed is the useful part.
        if (is_string($value) && mb_strlen($value) > 300) {
            return mb_substr($value, 0, 300) . '…';
        }

        return $value;
    }

    /**
     * Request data is absent in console and queued contexts.
     */
    private function requestValue(callable $resolver): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        try {
            $value = $resolver();

            return is_string($value) ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }
}
