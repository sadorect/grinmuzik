<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'artist_profile_id',
        'track_id',
        'amount',
        'currency',
        'payout_currency',
        'stripe_payment_intent_id',
        'type',
        'status',
        'message',
        'provider',
        'play_purchase_token',
        'play_product_id',
        'play_acknowledged_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'currency' => 'string',
        'payout_currency' => 'string',
        'play_acknowledged_at' => 'datetime',
    ];

    public function ledgerEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ArtistLedgerEntry::class);
    }

    public function scopeProvider(\Illuminate\Database\Eloquent\Builder $query, string $provider): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * Play purchases Google will auto-refund unless acknowledged.
     */
    public function scopeAwaitingPlayAcknowledgement(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('provider', 'play')
            ->whereNotNull('play_purchase_token')
            ->whereNull('play_acknowledged_at');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function artist(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ArtistProfile::class, 'artist_profile_id');
    }

    public function track(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function scopeCompleted(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeUnlocks(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('type', 'unlock');
    }

    public function scopeTips(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('type', 'tip');
    }
}
