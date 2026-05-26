<?php

namespace App\Support\Charts;

use App\Models\Track;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ChartRankingService
{
    public function rankedTracks(?CarbonInterface $start, ?CarbonInterface $end, ?string $genreSlug = null, int $limit = 100): Collection
    {
        $query = Track::query()
            ->with(['artistProfile.user', 'genre'])
            ->withCount('likedByUsers as likes_count')
            ->where('is_published', true)
            ->when($genreSlug, fn (Builder $query) => $query->whereHas('genre', fn (Builder $genreQuery) => $genreQuery->where('slug', $genreSlug)));

        if ($start && $end) {
            $query->whereHas('playHistories', fn (Builder $plays) => $plays->whereBetween('created_at', [$start, $end]))
                ->withCount([
                    'playHistories as chart_plays' => fn (Builder $plays) => $plays->whereBetween('created_at', [$start, $end]),
                ])
                ->orderByDesc('chart_plays');
        } else {
            $query->withCount('playHistories as chart_plays')
                ->orderByDesc('play_count');
        }

        return $query
            ->orderByDesc('likes_count')
            ->orderByDesc('downloads_count')
            ->orderByDesc('id')
            ->take($limit)
            ->get()
            ->values()
            ->map(function (Track $track, int $index) use ($start, $end): array {
                $plays = (int) (($start && $end) ? ($track->chart_plays ?? 0) : ($track->play_count ?? 0));
                $likes = (int) ($track->likes_count ?? 0);
                $downloads = (int) ($track->downloads_count ?? 0);

                return [
                    'id' => $track->id,
                    'rank' => $index + 1,
                    'track' => $track,
                    'plays' => $plays,
                    'likes_count' => $likes,
                    'downloads_count' => $downloads,
                    'score' => $this->score($plays, $likes, $downloads),
                ];
            });
    }

    public function movementMap(iterable $currentRankings, iterable $previousRankings): array
    {
        $previousRanks = collect($previousRankings)->mapWithKeys(fn (array $row) => [$row['id'] => $row['rank']]);

        return collect($currentRankings)->mapWithKeys(function (array $row) use ($previousRanks): array {
            $previousRank = $previousRanks->get($row['id']);

            if (! $previousRank) {
                return [$row['id'] => ['type' => 'new', 'label' => 'New']];
            }

            $delta = $previousRank - $row['rank'];

            if ($delta > 0) {
                return [$row['id'] => ['type' => 'up', 'label' => "+{$delta}"]];
            }

            if ($delta < 0) {
                return [$row['id'] => ['type' => 'down', 'label' => (string) $delta]];
            }

            return [$row['id'] => ['type' => 'steady', 'label' => 'Hold']];
        })->all();
    }

    private function score(int $plays, int $likes, int $downloads): float
    {
        return round($plays + ($likes * 4.5) + ($downloads * 6), 2);
    }
}