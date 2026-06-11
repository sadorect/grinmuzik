<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\TrackResource;
use App\Models\ArtistProfile;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        $type  = $request->input('type', 'all');

        if ($query === '') {
            return response()->json(['tracks' => [], 'artists' => []]);
        }

        $like = '%' . addcslashes($query, '%_\\') . '%';

        $tracks  = [];
        $artists = [];

        if (in_array($type, ['all', 'tracks'], true)) {
            $results = Track::query()
                ->published()
                ->where(fn ($q) => $q->where('title', 'LIKE', $like)->orWhere('description', 'LIKE', $like))
                ->with(['artistProfile:id,slug,stage_name', 'genre:id,slug,name'])
                ->orderByDesc('play_count')
                ->limit(10)
                ->get();

            $tracks = TrackResource::collection($results);
        }

        if (in_array($type, ['all', 'artists'], true)) {
            $results = ArtistProfile::query()
                ->publiclyDiscoverable()
                ->where(fn ($q) => $q->where('stage_name', 'LIKE', $like)->orWhere('bio', 'LIKE', $like))
                ->with('genres:id,slug,name')
                ->withCount(['tracks as published_tracks_count' => fn ($q) => $q->published()])
                ->orderByDesc('total_plays')
                ->limit(10)
                ->get();

            $artists = ArtistResource::collection($results);
        }

        return response()->json(['tracks' => $tracks, 'artists' => $artists]);
    }
}
