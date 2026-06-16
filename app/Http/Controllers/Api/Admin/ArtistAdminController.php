<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArtistProfile;
use App\Notifications\ArtistNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArtistAdminController extends Controller
{
    public function destroy(Request $request, ArtistProfile $artist): JsonResponse
    {
        $request->validate(['confirm' => 'required|boolean']);

        if (! $request->boolean('confirm')) {
            return response()->json([
                'warning'   => 'This will soft-delete the artist and all their tracks. Pass confirm=true to proceed.',
                'artist'    => $artist->slug,
                'tracks'    => $artist->tracks()->count(),
            ]);
        }

        $trackCount = $artist->tracks()->count();

        $artist->tracks()->each(fn ($track) => $track->delete());
        $artist->delete();

        return response()->json([
            'message'         => 'Artist deleted',
            'affected_tracks' => $trackCount,
        ]);
    }

    public function verify(ArtistProfile $artist): JsonResponse
    {
        $artist->update(['is_verified' => true]);

        return response()->json([
            'message'     => 'Artist verified',
            'slug'        => $artist->slug,
            'stage_name'  => $artist->stage_name,
            'is_verified' => true,
        ]);
    }

    public function notify(Request $request, ArtistProfile $artist): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $artist->loadMissing('user');

        abort_unless($artist->user !== null, 422, 'Artist has no associated user account');

        $artist->user->notify(new ArtistNotification(
            $request->input('subject'),
            $request->input('message'),
        ));

        return response()->json([
            'message'    => 'Notification queued',
            'recipient'  => $artist->user->email,
            'subject'    => $request->input('subject'),
        ]);
    }
}
