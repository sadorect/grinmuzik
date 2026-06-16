<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlaySpikesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $hours = max(1, (int) $request->input('hours', 24));

        $windowStart   = now()->subHours($hours);
        $baselineStart = now()->subDays(14);

        // Plays in the spike window per track
        $windowPlays = DB::table('play_histories')
            ->select('track_id', DB::raw('count(*) as plays'))
            ->where('created_at', '>=', $windowStart)
            ->groupBy('track_id')
            ->pluck('plays', 'track_id');

        if ($windowPlays->isEmpty()) {
            return response()->json(['hours' => $hours, 'spikes' => []]);
        }

        // Average hourly rate over the baseline period (14 days) per track
        $baselineHours = 14 * 24;
        $baselinePlays = DB::table('play_histories')
            ->select('track_id', DB::raw('count(*) as plays'))
            ->where('created_at', '>=', $baselineStart)
            ->whereIn('track_id', $windowPlays->keys())
            ->groupBy('track_id')
            ->pluck('plays', 'track_id');

        $spikes = $windowPlays->filter(function ($windowCount, $trackId) use ($baselinePlays, $baselineHours, $hours) {
            $avgHourly = ($baselinePlays->get($trackId, 1)) / $baselineHours;
            $currentHourly = $windowCount / $hours;
            return $currentHourly > $avgHourly * 2;
        });

        if ($spikes->isEmpty()) {
            return response()->json(['hours' => $hours, 'spikes' => []]);
        }

        $tracks = Track::whereIn('id', $spikes->keys())
            ->with('artistProfile:id,slug,stage_name')
            ->get()
            ->map(fn (Track $track) => [
                'slug'             => $track->slug,
                'title'            => $track->title,
                'artist'           => $track->artistProfile?->stage_name,
                'window_plays'     => $spikes->get($track->id, 0),
                'baseline_hourly'  => round(($baselinePlays->get($track->id, 0)) / $baselineHours, 2),
                'current_hourly'   => round($spikes->get($track->id, 0) / $hours, 2),
            ]);

        return response()->json([
            'hours'  => $hours,
            'spikes' => $tracks->values(),
        ]);
    }
}
