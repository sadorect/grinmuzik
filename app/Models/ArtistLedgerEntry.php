<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement on an artist's balance.
 *
 * Append-only by convention: corrections are new `adjustment` rows, never
 * edits, so the history of what was owed and when stays reconstructable.
 */
class ArtistLedgerEntry extends Model
{
    public const TYPE_CREDIT = 'credit';

    public const TYPE_PAYOUT = 'payout';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'artist_profile_id',
        'donation_id',
        'type',
        'provider',
        'currency',
        'gross_amount',
        'provider_fee',
        'platform_fee',
        'net_amount',
        'description',
        'settled_at',
        'payout_reference',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'provider_fee' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    public function artistProfile(): BelongsTo
    {
        return $this->belongsTo(ArtistProfile::class);
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    /**
     * What the platform currently owes an artist.
     *
     * Credits are positive and payouts negative, so the balance is a single
     * SUM rather than a difference between two queries that can disagree.
     */
    public static function balanceFor(int $artistProfileId): float
    {
        return (float) static::query()
            ->where('artist_profile_id', $artistProfileId)
            ->sum('net_amount');
    }

    public function scopeUnsettled(Builder $query): Builder
    {
        return $query->whereNull('settled_at');
    }

    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CREDIT);
    }
}
