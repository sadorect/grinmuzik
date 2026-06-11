<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlbumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug'         => $this->slug,
            'title'        => $this->title,
            'type'         => $this->type,
            'release_date' => $this->release_date?->toDateString(),
            'description'  => $this->when(filled($this->description), $this->description),
            'cover_url'    => $this->getCoverUrl(),
            'tracks_count' => $this->public_tracks_count ?? $this->whenLoaded('tracks', fn () => $this->tracks->count()),
            'artist'       => $this->whenLoaded('artist', fn () => $this->artist ? [
                'slug' => $this->artist->slug,
                'name' => $this->artist->display_name,
            ] : null),
            'genre'        => $this->whenLoaded('genre', fn () => $this->genre ? [
                'slug' => $this->genre->slug,
                'name' => $this->genre->name,
            ] : null),
            'tracks'       => TrackResource::collection($this->whenLoaded('tracks')),
            'url'          => route('album.show', $this->slug),
        ];
    }
}
