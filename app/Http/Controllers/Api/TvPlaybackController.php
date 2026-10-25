<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayHistory;
use App\Models\SiteSetting;
use App\Models\Track;
use App\Support\AutoQueue;
use App\Support\Tv\TvPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Playing music on the TV. Like the website, no account is needed to listen,
 * and a signed-out listener's plays still count; a signed-in one also gets
 * them in their history.
 */
class TvPlaybackController extends Controller
{
    /**
     * A playable URL for one track, plus what plays after it. The queue
     * carries no audio URLs: they are signed and short-lived, so the TV asks
     * for each track's URL when it gets there.
     */
    public function show(Track $track): JsonResponse
    {
        abort_unless($track->is_published, 404);

        $audioUrl = $track->getAudioUrl();

        abort_if($audioUrl === '', 404);

        $track->load(TvPayload::TRACK_WITH);

        return response()->json([
            'track' => TvPayload::track($track),
            'audio_url' => $audioUrl,
            // Fetch a new URL after this, e.g. when resuming a long-paused track.
            'audio_url_expires_at' => $track->audioUrlExpiry()->toIso8601String(),
            // The TV reports a play once the listener passes this, as the web player does.
            'play_count_min_seconds' => SiteSetting::playCountMinSeconds(),
            'up_next' => SiteSetting::autoPlaylistEnabled() ? $this->upNext($track) : [],
        ]);
    }

    /** Same rule as the web player: count once past the threshold or at the end. */
    public function record(Request $request, Track $track): JsonResponse
    {
        abort_unless($track->is_published, 404);

        $data = $request->validate([
            'seconds_played' => 'required|integer|min:0',
            'completed' => 'nullable|boolean',
        ]);

        $seconds = (int) $data['seconds_played'];
        $counted = $track->qualifiesAsPlay($seconds, (bool) ($data['completed'] ?? false));

        if ($counted) {
            $user = $request->user('sanctum');

            if ($user && ($user->tokenCan('listen') || $user->tokenCan('write'))) {
                PlayHistory::create([
                    'user_id' => $user->id,
                    'track_id' => $track->id,
                    'ip_address' => $request->ip(),
                    'seconds_played' => $seconds,
                    'source' => 'tv',
                ]);
            }

            $track->incrementPlayCount();
        }

        return response()->json(['counted' => $counted]);
    }

    private function upNext(Track $seed): array
    {
        $ids = AutoQueue::trackIdsFor($seed);

        if ($ids === []) {
            return [];
        }

        $tracks = Track::published()
            ->whereIn('id', $ids)
            ->with(TvPayload::TRACK_WITH)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn (int $id) => $tracks->get($id))
            ->filter()
            ->map(fn (Track $track) => TvPayload::track($track))
            ->values()
            ->all();
    }
}
