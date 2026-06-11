<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArtistResource;
use App\Models\ArtistProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArtistController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->input('per_page', 20), 50);

        $artists = ArtistProfile::query()
            ->publiclyDiscoverable()
            ->with('genres:id,slug,name')
            ->withCount(['tracks as published_tracks_count' => fn ($q) => $q->published()])
            ->orderByDesc('total_plays')
            ->paginate($perPage);

        return ArtistResource::collection($artists);
    }

    public function show(ArtistProfile $artist): ArtistResource
    {
        $artist->load([
            'genres:id,slug,name',
            'tracks' => fn ($q) => $q->published()
                ->with(['genre:id,slug,name', 'artistProfile:id,slug,stage_name'])
                ->orderByDesc('play_count')
                ->limit(20),
        ]);

        $artist->loadCount(['tracks as published_tracks_count' => fn ($q) => $q->published()]);

        return new ArtistResource($artist);
    }
}
