<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageView extends Model
{
    protected $fillable = [
        'path',
        'session_id',
        'user_id',
        'ip_address',
        'user_agent',
        'country',
        'country_code',
        'region',
        'city',
        'timezone',
        'latitude',
        'longitude',
        'location_source',
        'resolved_location_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'resolved_location_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeLastDays($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days - 1)->startOfDay());
    }

    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_location_at');
    }

    public function getLocationLabelAttribute(): string
    {
        $parts = array_values(array_filter([
            $this->city,
            $this->region,
            $this->country,
        ]));

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return match ($this->location_source) {
            'private' => 'Private network',
            default => 'Location pending',
        };
    }

    public function getCoordinatesAttribute(): ?string
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return number_format($this->latitude, 4) . ', ' . number_format($this->longitude, 4);
    }
}
