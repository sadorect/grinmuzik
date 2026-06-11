<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlbumResource;
use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AlbumController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->input('per_page', 20), 50);

        $albums = Album::query()
            ->publiclyVisible()
            ->with(['artist:id,slug,stage_name', 'genre:id,slug,name'])
            ->withPublicTrackCount()
            ->when($request->input('artist'), fn ($q, $slug) => $q->whereHas('artist', fn ($a) => $a->where('slug', $slug)))
            ->when($request->input('genre'), fn ($q, $slug) => $q->whereHas('genre', fn ($g) => $g->where('slug', $slug)))
            ->when($request->input('type'), fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('release_date')
            ->paginate($perPage);

        return AlbumResource::collection($albums);
    }

    public function show(Album $album): AlbumResource
    {
        abort_unless($album->is_published && in_array($album->type, Album::PUBLIC_TYPES, true), 404);

        $album->load([
            'artist:id,slug,stage_name',
            'genre:id,slug,name',
            'tracks' => fn ($q) => $q->published()->with('genre:id,slug,name')->orderBy('track_number'),
        ]);

        return new AlbumResource($album);
    }
}
