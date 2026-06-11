<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $period = in_array($request->get('period'), ['week', 'month'], true)
            ? $request->get('period')
            : 'week';

        $limit = min((int) $request->get('limit', 20), 100);

        $snapshot = ChartSnapshot::query()
            ->where('period_type', $period)
            ->latest('generated_at')
            ->first();

        if (! $snapshot) {
            return response()->json(['snapshot' => null, 'entries' => []]);
        }

        $entries = $snapshot->entries()
            ->with(['track:id,slug,title,play_count,duration,artist_profile_id', 'track.artistProfile:id,slug,stage_name'])
            ->limit($limit)
            ->get()
            ->map(fn ($entry) => [
                'rank'           => $entry->rank,
                'previous_rank'  => $entry->previous_rank,
                'movement'       => $entry->movement,
                'movement_label' => $entry->movement_label,
                'plays'          => $entry->plays,
                'likes'          => $entry->likes_count,
                'downloads'      => $entry->downloads_count,
                'score'          => $entry->score,
                'track'          => $entry->track ? [
                    'slug'      => $entry->track->slug,
                    'title'     => $entry->track->title,
                    'duration'  => $entry->track->formatted_duration,
                    'artist'    => $entry->track->artistProfile
                        ? ['slug' => $entry->track->artistProfile->slug, 'name' => $entry->track->artistProfile->stage_name]
                        : null,
                    'url'       => route('track.show', $entry->track->slug),
                ] : null,
            ]);

        return response()->json([
            'snapshot' => [
                'period'       => $snapshot->period_type,
                'title'        => $snapshot->display_title,
                'date_range'   => $snapshot->date_range_label,
                'generated_at' => $snapshot->generated_at?->toIso8601String(),
                'total_tracks' => $snapshot->total_tracks,
                'total_plays'  => $snapshot->total_plays,
            ],
            'entries' => $entries,
        ]);
    }
}
