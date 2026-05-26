<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartSnapshot extends Model
{
    use HasFactory;

    public const PERIOD_WEEK = 'week';

    public const PERIOD_MONTH = 'month';

    protected $fillable = [
        'period_type',
        'period_key',
        'period_start',
        'period_end',
        'generated_at',
        'previous_snapshot_id',
        'total_tracks',
        'total_plays',
        'total_likes',
        'total_downloads',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'generated_at' => 'datetime',
        'total_tracks' => 'integer',
        'total_plays' => 'integer',
        'total_likes' => 'integer',
        'total_downloads' => 'integer',
    ];

    public static function periodTypeOptions(): array
    {
        return [
            static::PERIOD_WEEK => 'Weekly',
            static::PERIOD_MONTH => 'Monthly',
        ];
    }

    public function previousSnapshot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_snapshot_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ChartSnapshotEntry::class)->orderBy('rank');
    }

    public function getPeriodTypeLabelAttribute(): string
    {
        return static::periodTypeOptions()[$this->period_type] ?? ucfirst($this->period_type);
    }

    public function getDisplayTitleAttribute(): string
    {
        if ($this->period_type === static::PERIOD_MONTH) {
            return $this->period_start?->format('F Y') ?? 'Monthly snapshot';
        }

        if (! $this->period_start || ! $this->period_end) {
            return 'Weekly snapshot';
        }

        return sprintf(
            'Week of %s',
            $this->period_start->format('M j, Y'),
        );
    }

    public function getDateRangeLabelAttribute(): string
    {
        if (! $this->period_start || ! $this->period_end) {
            return 'Unknown range';
        }

        if ($this->period_type === static::PERIOD_MONTH) {
            return $this->period_start->format('M j') . ' - ' . $this->period_end->format('M j, Y');
        }

        return $this->period_start->format('D, M j') . ' - ' . $this->period_end->format('D, M j, Y');
    }
}