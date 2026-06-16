<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArtistProfile;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $failedJobs = DB::table('failed_jobs')->count();
        $queueSize  = DB::table('jobs')->count();

        $tracksMissingCover     = Track::published()->whereDoesntHave('media', fn ($q) => $q->where('collection_name', 'cover'))->count();
        $tracksMissingDuration  = Track::published()->where(fn ($q) => $q->whereNull('duration')->orWhere('duration', 0))->count();
        $tracksMissingGenre     = Track::published()->whereNull('genre_id')->count();
        $unverifiedArtists      = ArtistProfile::where('is_verified', false)->where('is_active', true)->count();

        $storageUsedGb = 0.0;
        try {
            $bytes = DB::table('media')->sum('size');
            $storageUsedGb = round($bytes / (1024 ** 3), 2);
        } catch (\Throwable) {
        }

        return response()->json([
            'new_artists_7d'          => ArtistProfile::where('created_at', '>=', now()->subDays(7))->count(),
            'new_tracks_7d'           => Track::where('created_at', '>=', now()->subDays(7))->count(),
            'total_plays_7d'          => (int) DB::table('play_histories')->where('created_at', '>=', now()->subDays(7))->count(),
            'failed_jobs'             => $failedJobs,
            'queue_size'              => $queueSize,
            'tracks_missing_cover'    => $tracksMissingCover,
            'tracks_missing_duration' => $tracksMissingDuration,
            'tracks_missing_genre'    => $tracksMissingGenre,
            'unverified_artists'      => $unverifiedArtists,
            'storage_used_gb'         => $storageUsedGb,
        ]);
    }
}
