<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AccountDeletionRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'email',
        'token_hash',
        'status',
        'requested_ip',
        'expires_at',
        'confirmed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Issue a request, returning the raw token to mail out.
     *
     * The raw token is returned and never persisted; only its hash is stored,
     * so a database leak cannot be replayed to delete anybody's account.
     *
     * @return array{0: self, 1: string}
     */
    public static function issueFor(User $user, ?string $ip = null): array
    {
        // One live request per user. Re-requesting supersedes the old link
        // rather than leaving several valid ones in various inboxes.
        self::query()
            ->where('user_id', $user->id)
            ->where('status', self::STATUS_PENDING)
            ->update(['status' => self::STATUS_CANCELLED]);

        $token = Str::random(64);

        $request = self::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token_hash' => hash('sha256', $token),
            'status' => self::STATUS_PENDING,
            'requested_ip' => $ip,
            'expires_at' => now()->addHours(
                (int) config('security.account_deletion.token_hours', 24),
            ),
        ]);

        return [$request, $token];
    }

    /**
     * Find the live request a raw token refers to, if any.
     *
     * Looks up by hash rather than iterating and comparing, so the query is
     * indexed; the token is high-entropy and single-use, so there is nothing to
     * gain from a timing-safe scan.
     */
    public static function findPendingByToken(string $token): ?self
    {
        return self::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('status', self::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->first();
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
