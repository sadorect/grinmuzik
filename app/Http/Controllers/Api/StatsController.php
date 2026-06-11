<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArtistProfile;
use App\Models\Track;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'total_tracks'    => Track::published()->count(),
            'total_artists'   => ArtistProfile::publiclyDiscoverable()->count(),
            'total_plays'     => (int) Track::published()->sum('play_count'),
            'total_downloads' => (int) Track::published()->sum('downloads_count'),
        ]);
    }
}
