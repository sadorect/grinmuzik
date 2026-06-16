<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrackResource;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackAdminController extends Controller
{
    public function feature(Request $request, Track $track): TrackResource
    {
        $request->validate(['featured' => 'required|boolean']);

        $track->update(['is_featured' => $request->boolean('featured')]);

        return new TrackResource($track->fresh(['artistProfile:id,slug,stage_name', 'genre:id,slug,name']));
    }

    public function update(Request $request, Track $track): TrackResource
    {
        $data = $request->validate([
            'title'      => 'sometimes|string|max:255',
            'mood'       => 'sometimes|nullable|string|max:100',
            'genre_slug' => 'sometimes|nullable|string',
            'is_free'    => 'sometimes|boolean',
        ]);

        if (isset($data['genre_slug'])) {
            $genre = \App\Models\Genre::where('slug', $data['genre_slug'])->first();
            $data['genre_id'] = $genre?->id;
            unset($data['genre_slug']);
        }

        $track->update($data);

        return new TrackResource($track->fresh(['artistProfile:id,slug,stage_name', 'genre:id,slug,name']));
    }

    public function publish(Track $track): JsonResponse
    {
        $track->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        return response()->json([
            'message'      => 'Track published successfully',
            'slug'         => $track->slug,
            'published_at' => $track->published_at,
        ]);
    }

    public function unpublish(Track $track): JsonResponse
    {
        $track->update(['is_published' => false]);

        return response()->json([
            'message' => 'Track unpublished',
            'slug'    => $track->slug,
        ]);
    }
}
