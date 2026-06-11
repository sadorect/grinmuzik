<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\JsonResponse;

class GenreController extends Controller
{
    public function index(): JsonResponse
    {
        $genres = Genre::query()
            ->active()
            ->withCount(['tracks as published_tracks_count' => fn ($q) => $q->published()])
            ->get()
            ->map(fn (Genre $g) => [
                'slug'         => $g->slug,
                'name'         => $g->name,
                'description'  => $g->description,
                'tracks_count' => $g->published_tracks_count,
            ]);

        return response()->json(['data' => $genres]);
    }
}
