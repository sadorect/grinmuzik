<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChartSnapshotEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'chart_snapshot_id',
        'track_id',
        'rank',
        'previous_rank',
        'movement',
        'movement_delta',
        'plays',
        'likes_count',
        'downloads_count',
        'score',
    ];

    protected $casts = [
        'rank' => 'integer',
        'previous_rank' => 'integer',
        'movement_delta' => 'integer',
        'plays' => 'integer',
        'likes_count' => 'integer',
        'downloads_count' => 'integer',
        'score' => 'float',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ChartSnapshot::class, 'chart_snapshot_id');
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function getMovementLabelAttribute(): string
    {
        return match ($this->movement) {
            'up' => '+' . abs((int) $this->movement_delta),
            'down' => (string) $this->movement_delta,
            'steady' => 'Hold',
            default => 'New',
        };
    }
}