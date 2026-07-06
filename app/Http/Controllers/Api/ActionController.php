<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlaylistResource;
use App\Models\Like;
use App\Models\PlayHistory;
use App\Models\Playlist;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActionController extends Controller
{
    public function recordPlay(Request $request, Track $track): JsonResponse
    {
        abort_unless($track->is_published, 404);

        $request->validate([
            'seconds_played' => 'nullable|integer|min:0',
            'source'         => 'nullable|string|max:50',
        ]);

        $user = $request->user();

        PlayHistory::create([
            'user_id'        => $user->id,
            'track_id'       => $track->id,
            'ip_address'     => $request->ip(),
            'seconds_played' => $request->integer('seconds_played', 0),
            'source'         => $request->input('source', 'mcp'),
        ]);

        $track->incrementPlayCount();

        return response()->json(['message' => 'Play recorded', 'play_count' => $track->play_count]);
    }

    public function toggleLike(Request $request, Track $track): JsonResponse
    {
        $user = $request->user();

        $existing = Like::where('user_id', $user->id)->where('track_id', $track->id)->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['liked' => false, 'message' => 'Track unliked']);
        }

        Like::create(['user_id' => $user->id, 'track_id' => $track->id]);

        return response()->json(['liked' => true, 'message' => 'Track liked']);
    }

    public function createPlaylist(Request $request): PlaylistResource
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_public'   => 'nullable|boolean',
        ]);

        $playlist = $request->user()->playlists()->create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_public'   => $data['is_public'] ?? false,
        ]);

        return new PlaylistResource($playlist);
    }

    public function addTrackToPlaylist(Request $request, Playlist $playlist, Track $track): JsonResponse
    {
        abort_unless($playlist->user_id === $request->user()->id, 403, 'You do not own this playlist');

        $maxPosition = $playlist->tracks()->max(DB::raw('playlist_track.position')) ?? 0;

        $playlist->tracks()->syncWithoutDetaching([
            $track->id => ['position' => $request->integer('position', $maxPosition + 1)],
        ]);

        return response()->json(['message' => 'Track added to playlist']);
    }

    public function removeTrackFromPlaylist(Request $request, Playlist $playlist, Track $track): JsonResponse
    {
        abort_unless($playlist->user_id === $request->user()->id, 403, 'You do not own this playlist');

        $playlist->tracks()->detach($track->id);

        return response()->json(['message' => 'Track removed from playlist']);
    }
}
