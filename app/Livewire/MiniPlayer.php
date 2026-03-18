<?php

namespace App\Livewire;

use App\Models\Like;
use App\Models\PlayHistory;
use App\Models\SiteSetting;
use App\Models\Track;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class MiniPlayer extends Component
{
    public ?int $trackId = null;
    public ?array $track = null;

    public bool $playing = false;
    public int $currentTime = 0;
    public int $duration = 0;
    public int $volume = 80;

    public bool $liked = false;
    public int $likesCount = 0;

    #[On('play-track')]
    public function loadTrack(int $id): void
    {
        $model = Track::with(['artistProfile.user', 'album'])->find($id);

        if (! $model || ! $model->is_published) {
            return;
        }

        $trackData = $this->makePlayerTrackPayload(
            $model,
            includeAutoQueue: SiteSetting::autoPlaylistEnabled(),
        );

        if (! $trackData) {
            return;
        }

        $this->trackId = $id;
        $this->playing = true;
        $this->track = $trackData;
        $this->duration = $model->duration ?? 0;

        if (Auth::check()) {
            PlayHistory::create([
                'user_id' => Auth::id(),
                'track_id' => $id,
                'ip_address' => request()->ip(),
                'source' => 'web',
            ]);
        }

        $model->incrementPlayCount();
        $this->refreshLikeState();

        $this->dispatch('player-track-loaded', track: $this->track);
    }

    public function likeToggle(): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-modal', 'login-required');

            return;
        }

        if (! $this->trackId) {
            return;
        }

        $exists = Like::where('user_id', Auth::id())
            ->where('track_id', $this->trackId)
            ->exists();

        if ($exists) {
            Like::where('user_id', Auth::id())
                ->where('track_id', $this->trackId)
                ->delete();
        } else {
            Like::create([
                'user_id' => Auth::id(),
                'track_id' => $this->trackId,
            ]);
        }

        $this->refreshLikeState();
    }

    public function downloadTrack(): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-modal', 'login-required');

            return;
        }

        $track = Track::find($this->trackId);

        if (! $track) {
            return;
        }

        $track->increment('downloads_count');

        $this->dispatch(
            'start-download',
            url: $track->getAudioUrl(),
            filename: $track->title . '.mp3'
        );
    }

    #[On('queue-track')]
    public function queueTrack(int $id): void
    {
        $model = Track::with(['artistProfile.user'])->find($id);

        if (! $model || ! $model->is_published) {
            return;
        }

        $trackData = $this->makeQueueTrackPayload($model);

        if (! $trackData) {
            return;
        }

        $this->dispatch('player-queue-add', track: $trackData);
    }

    #[On('play-playlist')]
    public function playPlaylist(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $tracks = Track::with(['artistProfile.user'])
            ->whereIn('id', $ids)
            ->where('is_published', true)
            ->get()
            ->sortBy(fn (Track $track) => array_search($track->id, $ids))
            ->values();

        $first = true;

        foreach ($tracks as $model) {
            $trackData = $this->makeQueueTrackPayload($model);

            if (! $trackData) {
                continue;
            }

            if ($first) {
                if (Auth::check()) {
                    PlayHistory::create([
                        'user_id' => Auth::id(),
                        'track_id' => $model->id,
                        'ip_address' => request()->ip(),
                        'source' => 'web',
                    ]);
                }

                $model->incrementPlayCount();
                $this->dispatch('player-track-loaded', track: $trackData);
                $first = false;

                continue;
            }

            $this->dispatch('player-queue-add', track: $trackData);
        }
    }

    #[On('queue-playlist')]
    public function queuePlaylist(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $tracks = Track::with(['artistProfile.user'])
            ->whereIn('id', $ids)
            ->where('is_published', true)
            ->get()
            ->sortBy(fn (Track $track) => array_search($track->id, $ids))
            ->values();

        foreach ($tracks as $model) {
            $trackData = $this->makeQueueTrackPayload($model);

            if (! $trackData) {
                continue;
            }

            $this->dispatch('player-queue-add', track: $trackData);
        }
    }

    #[On('player-paused')]
    public function onPaused(): void
    {
        $this->playing = false;
    }

    #[On('player-resumed')]
    public function onResumed(): void
    {
        $this->playing = true;
    }

    public function render()
    {
        return view('livewire.mini-player');
    }

    protected function refreshLikeState(): void
    {
        if (! $this->trackId) {
            return;
        }

        $this->liked = Auth::check()
            && Like::where('user_id', Auth::id())
                ->where('track_id', $this->trackId)
                ->exists();

        $this->likesCount = Like::where('track_id', $this->trackId)->count();
    }

    protected function makePlayerTrackPayload(Track $model, bool $includeAutoQueue = false): ?array
    {
        $audioUrl = $model->getAudioUrl();

        if (! $audioUrl) {
            return null;
        }

        return [
            'id' => $model->id,
            'title' => $model->title,
            'artist' => $model->artistProfile?->stage_name
                ?? $model->artistProfile?->user?->name
                ?? 'Unknown',
            'cover' => $model->getCoverUrl(),
            'audio_url' => $audioUrl,
            'url' => route('track.show', $model->slug),
            'duration' => $model->duration ?? 0,
            'requires_donation' => $model->requires_donation,
            'donation_amount' => $model->donation_amount,
            'auto_queue_tracks' => $includeAutoQueue
                ? $this->buildAutoQueueTracks($model)
                : [],
        ];
    }

    protected function makeQueueTrackPayload(Track $model): ?array
    {
        $payload = $this->makePlayerTrackPayload($model);

        if (! $payload) {
            return null;
        }

        unset(
            $payload['url'],
            $payload['requires_donation'],
            $payload['donation_amount'],
            $payload['auto_queue_tracks'],
        );

        return $payload;
    }

    protected function buildAutoQueueTracks(Track $seedTrack, int $limit = 10): array
    {
        $queue = [];
        $seenIds = [$seedTrack->id];
        $baseQuery = fn () => Track::query()
            ->with(['artistProfile.user'])
            ->published()
            ->orderByDesc('is_featured')
            ->orderByDesc('play_count')
            ->orderByDesc('downloads_count')
            ->orderByDesc('created_at');

        if ($seedTrack->album_id) {
            $this->appendAutoQueueCandidates(
                $baseQuery()->where('album_id', $seedTrack->album_id),
                $queue,
                $seenIds,
                $limit,
            );
        }

        if ($seedTrack->artist_profile_id) {
            $this->appendAutoQueueCandidates(
                $baseQuery()->where('artist_profile_id', $seedTrack->artist_profile_id),
                $queue,
                $seenIds,
                $limit,
            );
        }

        if ($seedTrack->genre_id) {
            $this->appendAutoQueueCandidates(
                $baseQuery()->where('genre_id', $seedTrack->genre_id),
                $queue,
                $seenIds,
                $limit,
            );
        }

        $this->appendAutoQueueCandidates(
            $baseQuery(),
            $queue,
            $seenIds,
            $limit,
        );

        return $queue;
    }

    protected function appendAutoQueueCandidates($query, array &$queue, array &$seenIds, int $limit): void
    {
        if (count($queue) >= $limit) {
            return;
        }

        $candidates = $query
            ->whereNotIn('id', $seenIds)
            ->take($limit - count($queue))
            ->get();

        foreach ($candidates as $candidate) {
            $payload = $this->makeQueueTrackPayload($candidate);

            if (! $payload || in_array($candidate->id, $seenIds, true)) {
                continue;
            }

            $queue[] = $payload;
            $seenIds[] = $candidate->id;

            if (count($queue) >= $limit) {
                break;
            }
        }
    }
}
