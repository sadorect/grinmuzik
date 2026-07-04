<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LatestTracksController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->input('limit', 20)));

        $tracks = Track::published()
            ->with(['artistProfile:id,slug,stage_name', 'genre:id,name'])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Track $track) => [
                'slug'         => $track->slug,
                'title'        => $track->title,
                'artist'       => $track->artistProfile?->stage_name,
                'genre'        => $track->genre?->name,
                'play_count'   => $track->play_count,
                'created_at'   => $track->created_at,
                'published_at' => $track->published_at,
            ]);

        return response()->json([
            'count'  => $tracks->count(),
            'tracks' => $tracks,
        ]);
    }
}
