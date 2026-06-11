<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug'        => $this->slug,
            'title'       => $this->title,
            'duration'    => $this->formatted_duration,
            'mood'        => $this->mood_label,
            'play_count'  => $this->play_count,
            'downloads'   => $this->downloads_count,
            'is_free'     => $this->is_free,
            'cover_url'   => $this->getCoverUrl(),
            'artist'      => $this->whenLoaded('artistProfile', fn () => [
                'slug' => $this->artistProfile->slug,
                'name' => $this->artistProfile->stage_name,
            ]),
            'genre'       => $this->whenLoaded('genre', fn () => $this->genre ? [
                'slug' => $this->genre->slug,
                'name' => $this->genre->name,
            ] : null),
            'album'       => $this->whenLoaded('album', fn () => $this->album ? [
                'slug'  => $this->album->slug,
                'title' => $this->album->title,
            ] : null),
            'description' => $this->when(filled($this->description), $this->description),
            'lyrics'      => $this->when(filled($this->lyrics), $this->lyrics),
            'url'         => route('track.show', $this->slug),
        ];
    }
}
