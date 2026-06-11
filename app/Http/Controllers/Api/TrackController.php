<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrackResource;
use App\Models\Track;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TrackController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->input('per_page', 20), 50);

        $tracks = Track::query()
            ->published()
            ->with(['artistProfile:id,slug,stage_name', 'genre:id,slug,name'])
            ->when($request->input('genre'), fn ($q, $slug) => $q->whereHas('genre', fn ($g) => $g->where('slug', $slug)))
            ->when($request->input('artist'), fn ($q, $slug) => $q->whereHas('artistProfile', fn ($a) => $a->where('slug', $slug)))
            ->when($request->input('mood'), fn ($q, $mood) => $q->where('mood', $mood))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return TrackResource::collection($tracks);
    }

    public function show(Track $track): TrackResource
    {
        $track->load(['artistProfile:id,slug,stage_name', 'genre:id,slug,name', 'album:id,slug,title']);

        return new TrackResource($track);
    }
}
