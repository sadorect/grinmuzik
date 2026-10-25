<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

/**
 * One TV sign-in attempt. See the create_tv_device_codes_table migration for
 * the flow.
 */
class TvDeviceCode extends Model
{
    use MassPrunable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DENIED = 'denied';

    /** The TV has collected its token; the code can never be used again. */
    public const STATUS_CONSUMED = 'consumed';

    /** The only thing a TV token may do: listener actions, never admin. */
    public const TOKEN_ABILITY = 'listen';

    /** Sanctum token names start with this, so the /tv page can list them. */
    public const TOKEN_NAME_PREFIX = 'tv:';

    // Consonants only, minus look-alikes: no vowels means no accidental words,
    // and nothing to confuse on a TV across the room (0/O, 1/I/L, 5/S, 2/Z).
    private const USER_CODE_ALPHABET = 'BCDFGHJKMNPQRTVWXY';

    protected $fillable = [
        'device_code_hash',
        'user_code',
        'device_name',
        'requested_ip',
        'user_id',
        'status',
        'expires_at',
        'approved_at',
        'last_polled_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'last_polled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Start a sign-in, returning the raw device code for the TV.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(?string $deviceName, ?string $ip): array
    {
        $deviceCode = Str::random(64);

        do {
            $userCode = self::randomUserCode();
        } while (self::where('user_code', $userCode)->exists());

        $code = self::create([
            'device_code_hash' => hash('sha256', $deviceCode),
            'user_code' => $userCode,
            'device_name' => $deviceName,
            'requested_ip' => $ip,
            'status' => self::STATUS_PENDING,
            'expires_at' => now()->addMinutes((int) config('security.tv.code_minutes', 10)),
        ]);

        return [$code, $deviceCode];
    }

    public static function findByDeviceCode(string $deviceCode): ?self
    {
        return self::where('device_code_hash', hash('sha256', $deviceCode))->first();
    }

    /**
     * The pending, unexpired code a person typed, in whatever case and with or
     * without the dash.
     */
    public static function findPendingByUserCode(string $typed): ?self
    {
        $normalized = self::normalizeUserCode($typed);

        if (strlen($normalized) !== 8) {
            return null;
        }

        return self::where('user_code', $normalized)
            ->where('status', self::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->first();
    }

    public static function normalizeUserCode(string $typed): string
    {
        return preg_replace('/[^A-Z]/', '', strtoupper($typed));
    }

    /** How the code is shown: two groups of four, easy to read off a TV. */
    public function displayUserCode(): string
    {
        return substr($this->user_code, 0, 4).'-'.substr($this->user_code, 4);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function approve(User $user): bool
    {
        return self::whereKey($this->id)
            ->where('status', self::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->update([
                'status' => self::STATUS_APPROVED,
                'user_id' => $user->id,
                'approved_at' => now(),
            ]) === 1;
    }

    public function deny(): bool
    {
        return self::whereKey($this->id)
            ->where('status', self::STATUS_PENDING)
            ->update(['status' => self::STATUS_DENIED]) === 1;
    }

    /**
     * Hand out the token for an approved code, exactly once.
     *
     * The status flip is a conditional update, so two polls racing each other
     * cannot both mint a token.
     */
    public function exchangeForToken(): ?NewAccessToken
    {
        $won = self::whereKey($this->id)
            ->where('status', self::STATUS_APPROVED)
            ->update(['status' => self::STATUS_CONSUMED]) === 1;

        if (! $won) {
            return null;
        }

        $name = self::TOKEN_NAME_PREFIX.($this->device_name ?: 'TV');

        return $this->user->createToken($name, [self::TOKEN_ABILITY]);
    }

    /** Spent or abandoned codes are of no use a day later. */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subDay());
    }

    private static function randomUserCode(): string
    {
        $alphabet = self::USER_CODE_ALPHABET;
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
