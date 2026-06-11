<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug'        => $this->slug,
            'name'        => $this->display_name,
            'bio'         => $this->when(filled($this->bio), $this->bio),
            'total_plays' => $this->total_plays,
            'followers'   => $this->followers_count,
            'tracks_count'=> $this->whenCounted('tracks', fn () => $this->tracks_count)
                               ?? $this->published_tracks_count
                               ?? 0,
            'is_verified' => $this->is_verified,
            'avatar_url'  => $this->getAvatarUrl(),
            'banner_url'  => $this->when($this->getBannerUrl() !== '', $this->getBannerUrl()),
            'genres'      => $this->whenLoaded('genres', fn () => $this->genres->map(fn ($g) => [
                'slug' => $g->slug,
                'name' => $g->name,
            ])->values()),
            'top_tracks'  => TrackResource::collection($this->whenLoaded('tracks')),
            'social'      => $this->when(
                $this->website_url || $this->instagram_url || $this->twitter_url || $this->youtube_url || $this->spotify_url,
                fn () => array_filter([
                    'website'   => $this->website_url,
                    'instagram' => $this->instagram_url,
                    'twitter'   => $this->twitter_url,
                    'youtube'   => $this->youtube_url,
                    'spotify'   => $this->spotify_url,
                ])
            ),
            'url'         => route('artist.page', $this->slug),
        ];
    }
}
