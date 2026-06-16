<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnderperformingTracksController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $threshold = (int) $request->input('threshold', 100);

        $tracks = Track::published()
            ->with('artistProfile:id,slug,stage_name')
            ->where('created_at', '<=', now()->subDays(14))
            ->where('play_count', '<', $threshold)
            ->orderBy('play_count')
            ->get()
            ->map(fn (Track $track) => [
                'slug'       => $track->slug,
                'title'      => $track->title,
                'artist'     => $track->artistProfile?->stage_name,
                'play_count' => $track->play_count,
                'age_days'   => (int) $track->created_at->diffInDays(now()),
            ]);

        return response()->json([
            'threshold' => $threshold,
            'count'     => $tracks->count(),
            'tracks'    => $tracks,
        ]);
    }
}
