<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArtistProfile;
use Illuminate\Http\JsonResponse;

class DormantArtistsController extends Controller
{
    public function index(): JsonResponse
    {
        $format = fn (ArtistProfile $artist) => [
            'id'         => $artist->id,
            'name'       => $artist->display_name,
            'email'      => $artist->user?->email,
            'slug'       => $artist->slug,
            'created_at' => $artist->created_at,
            'warned_at'  => $artist->warned_at,
        ];

        return response()->json([
            'pending_warning'  => ArtistProfile::pendingDormancyWarning()->with('user')->get()->map($format)->values(),
            'pending_demotion' => ArtistProfile::pendingDormancyDemotion()->with('user')->get()->map($format)->values(),
        ]);
    }
}
