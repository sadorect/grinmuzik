<?php

namespace App\Console\Commands;

use App\Models\ChartSnapshot;
use App\Support\Charts\ChartSnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class GenerateChartSnapshotsCommand extends Command
{
    protected $signature = 'charts:snapshot
        {period=week : The chart period to snapshot (week, month, or all)}
        {--date= : Generate the period containing this date (Y-m-d)}
        {--limit=100 : The maximum number of ranked tracks to archive}';

    protected $description = 'Archive immutable weekly or monthly chart snapshots.';

    public function handle(ChartSnapshotService $snapshotService): int
    {
        $periodInput = strtolower((string) $this->argument('period'));
        $periods = $periodInput === 'all'
            ? [ChartSnapshot::PERIOD_WEEK, ChartSnapshot::PERIOD_MONTH]
            : [$periodInput];

        try {
            $referenceDate = $this->option('date')
                ? CarbonImmutable::parse((string) $this->option('date'))
                : null;
        } catch (Throwable) {
            $this->components->error('The --date option must be a valid date string.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $exact = $referenceDate !== null;

        foreach ($periods as $period) {
            try {
                $normalizedPeriod = $snapshotService->normalizePeriodType($period);
                $resolved = $snapshotService->resolvePeriod($normalizedPeriod, $referenceDate, $exact);
                $existing = $snapshotService->findSnapshot($normalizedPeriod, $referenceDate, $exact);

                if ($existing) {
                    $this->components->info(sprintf(
                        'Skipped %s snapshot for %s. Immutable record already exists.',
                        $existing->period_type_label,
                        $existing->display_title,
                    ));

                    continue;
                }

                $snapshot = $snapshotService->createSnapshot($normalizedPeriod, $referenceDate, $exact, $limit);

                $this->components->info(sprintf(
                    'Created %s snapshot for %s (%s to %s) with %d tracks.',
                    $snapshot->period_type_label,
                    $snapshot->display_title,
                    $resolved['start']->toDateString(),
                    $resolved['end']->toDateString(),
                    $snapshot->total_tracks,
                ));
            } catch (Throwable $exception) {
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}