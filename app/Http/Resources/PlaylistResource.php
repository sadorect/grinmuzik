<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaylistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug'        => $this->slug,
            'name'        => $this->name,
            'description' => $this->when(filled($this->description), $this->description),
            'is_public'   => $this->is_public,
            'cover_url'   => $this->getCoverUrl(),
            'tracks_count'=> $this->whenCounted('tracks') ?? $this->whenLoaded('tracks', fn () => $this->tracks->count()),
            'owner'       => $this->whenLoaded('user', fn () => $this->user ? [
                'name' => $this->user->name,
            ] : null),
            'tracks'      => TrackResource::collection($this->whenLoaded('tracks')),
            'url'         => route('playlist.show', $this->slug),
        ];
    }
}
