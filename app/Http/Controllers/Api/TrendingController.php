<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrendingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 10), 50);

        $since = now()->subHours(48);

        // Plays per track in the last 48 hours
        $velocities = DB::table('play_histories')
            ->select('track_id', DB::raw('count(*) as plays'))
            ->where('created_at', '>=', $since)
            ->groupBy('track_id')
            ->orderByDesc('plays')
            ->limit($limit)
            ->get()
            ->keyBy('track_id');

        if ($velocities->isEmpty()) {
            return response()->json(['tracks' => []]);
        }

        $tracks = Track::published()
            ->whereIn('id', $velocities->keys())
            ->with(['artistProfile:id,slug,stage_name', 'genre:id,slug,name'])
            ->get();

        $ranked = $tracks->map(fn (Track $track) => [
            'slug'           => $track->slug,
            'title'          => $track->title,
            'artist'         => $track->artistProfile?->stage_name,
            'genre'          => $track->genre?->name,
            'play_count'     => $track->play_count,
            'velocity_score' => (int) ($velocities->get($track->id)?->plays ?? 0),
            'cover_url'      => $track->getCoverUrl(),
        ])->sortByDesc('velocity_score')->values();

        return response()->json(['tracks' => $ranked]);
    }
}
