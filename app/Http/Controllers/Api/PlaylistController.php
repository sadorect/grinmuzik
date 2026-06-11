<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlaylistResource;
use App\Models\Playlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlaylistController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->get('per_page', 20), 50);

        $playlists = Playlist::query()
            ->public()
            ->with('user:id,name')
            ->withCount('tracks')
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        return PlaylistResource::collection($playlists);
    }

    public function show(Playlist $playlist): PlaylistResource
    {
        abort_unless($playlist->is_public, 404);

        $playlist->load([
            'user:id,name',
            'tracks' => fn ($q) => $q->published()->with(['artistProfile:id,slug,stage_name', 'genre:id,slug,name']),
        ]);

        return new PlaylistResource($playlist);
    }
}
