<?php

namespace App\Support\Charts;

use App\Models\ChartSnapshot;
use App\Support\Charts\ChartRankingService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ChartSnapshotService
{
    public function __construct(private readonly ChartRankingService $rankingService)
    {
    }

    public function findSnapshot(string $periodType, ?CarbonInterface $reference = null, bool $exact = false): ?ChartSnapshot
    {
        $period = $this->resolvePeriod($periodType, $reference, $exact);

        return ChartSnapshot::query()
            ->where('period_type', $period['type'])
            ->where('period_key', $period['key'])
            ->first();
    }

    public function createSnapshot(string $periodType, ?CarbonInterface $reference = null, bool $exact = false, int $limit = 100): ChartSnapshot
    {
        $period = $this->resolvePeriod($periodType, $reference, $exact);

        $existing = ChartSnapshot::query()
            ->where('period_type', $period['type'])
            ->where('period_key', $period['key'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $rankings = $this->rankingService->rankedTracks($period['start'], $period['end'], null, $limit);
        $previousSnapshot = ChartSnapshot::query()
            ->where('period_type', $period['type'])
            ->where('period_end', '<', $period['start'])
            ->with('entries')
            ->latest('period_end')
            ->first();
        $previousRanks = $previousSnapshot?->entries
            ?->pluck('rank', 'track_id')
            ->map(fn (mixed $rank): int => (int) $rank)
            ->all() ?? [];
        $now = now();

        return DB::transaction(function () use ($period, $rankings, $previousSnapshot, $previousRanks, $now): ChartSnapshot {
            $snapshot = ChartSnapshot::create([
                'period_type' => $period['type'],
                'period_key' => $period['key'],
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'generated_at' => $now,
                'previous_snapshot_id' => $previousSnapshot?->id,
                'total_tracks' => $rankings->count(),
                'total_plays' => (int) $rankings->sum('plays'),
                'total_likes' => (int) $rankings->sum('likes_count'),
                'total_downloads' => (int) $rankings->sum('downloads_count'),
            ]);

            $snapshot->entries()->insert(
                $rankings->map(function (array $row) use ($previousRanks, $snapshot, $now): array {
                    $previousRank = $previousRanks[$row['id']] ?? null;
                    $movementDelta = $previousRank ? ($previousRank - $row['rank']) : null;

                    return [
                        'chart_snapshot_id' => $snapshot->id,
                        'track_id' => $row['id'],
                        'rank' => $row['rank'],
                        'previous_rank' => $previousRank,
                        'movement' => $this->movementType($previousRank, $row['rank']),
                        'movement_delta' => $movementDelta,
                        'plays' => $row['plays'],
                        'likes_count' => $row['likes_count'],
                        'downloads_count' => $row['downloads_count'],
                        'score' => $row['score'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all()
            );

            return $snapshot->load(['entries.track.artistProfile.user', 'entries.track.genre', 'previousSnapshot']);
        });
    }

    public function resolvePeriod(string $periodType, ?CarbonInterface $reference = null, bool $exact = false): array
    {
        $type = $this->normalizePeriodType($periodType);
        $anchor = $reference ? CarbonImmutable::instance($reference) : CarbonImmutable::now();

        if ($type === ChartSnapshot::PERIOD_MONTH) {
            $periodDate = $exact ? $anchor : $anchor->subMonthNoOverflow();
            $start = $periodDate->startOfMonth();
            $end = $periodDate->endOfMonth();

            return [
                'type' => $type,
                'start' => $start,
                'end' => $end,
                'key' => $start->format('Y-m'),
            ];
        }

        $periodDate = $exact ? $anchor : $anchor->subWeek();
        $start = $periodDate->startOfWeek();
        $end = $periodDate->endOfWeek();

        return [
            'type' => $type,
            'start' => $start,
            'end' => $end,
            'key' => sprintf('%s-W%02d', $start->isoWeekYear, $start->isoWeek),
        ];
    }

    public function normalizePeriodType(string $periodType): string
    {
        $normalized = strtolower(trim($periodType));

        if (! in_array($normalized, [ChartSnapshot::PERIOD_WEEK, ChartSnapshot::PERIOD_MONTH], true)) {
            throw new InvalidArgumentException('Period must be week or month.');
        }

        return $normalized;
    }

    private function movementType(?int $previousRank, int $currentRank): string
    {
        if (! $previousRank) {
            return 'new';
        }

        if ($previousRank > $currentRank) {
            return 'up';
        }

        if ($previousRank < $currentRank) {
            return 'down';
        }

        return 'steady';
    }
}