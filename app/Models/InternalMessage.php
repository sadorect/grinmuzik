<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternalMessage extends Model
{
    protected $fillable = [
        'created_by',
        'recipient_user_id',
        'subject',
        'body',
        'audience',
        'send_email',
        'sent_at',
    ];

    protected $casts = [
        'send_email' => 'boolean',
        'sent_at'    => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(InternalMessageRead::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeSent(Builder $query): Builder
    {
        return $query->whereNotNull('sent_at');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->whereNull('sent_at');
    }

    /** Returns only messages that target the given user's role(s) OR sent directly to them. */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->sent()->where(function (Builder $q) use ($user): void {
            // Direct message to this specific user
            $q->where('recipient_user_id', $user->id);

            // Broadcast messages (no specific recipient) that match user's role
            $q->orWhere(function (Builder $q2) use ($user): void {
                $q2->whereNull('recipient_user_id');
                $q2->where(function (Builder $q3) use ($user): void {
                    $q3->where('audience', 'all');
                    if ($user->isArtist()) {
                        $q3->orWhere('audience', 'artists');
                    }
                    if ($user->isListener()) {
                        $q3->orWhere('audience', 'listeners');
                    }
                });
            });
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    public function isReadBy(User $user): bool
    {
        return $this->reads()->where('user_id', $user->id)->exists();
    }

    public function audienceLabel(): string
    {
        if ($this->recipient_user_id) {
            return $this->recipient?->name ?? 'Specific user';
        }

        return match ($this->audience) {
            'artists'   => 'Artists only',
            'listeners' => 'Listeners only',
            default     => 'All users',
        };
    }

    /**
     * Replace {name}, {username}, {email} placeholders for a specific recipient.
     */
    public function interpolate(string $text, User $user): string
    {
        return str_ireplace(
            ['{name}', '{username}', '{email}'],
            [$user->name, $user->name, $user->email],
            $text,
        );
    }
}
