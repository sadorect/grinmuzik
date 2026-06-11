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
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'currency' => 'string',
        'payout_currency' => 'string',
    ];

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
