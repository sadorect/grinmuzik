<?php

namespace App\Livewire;

use App\Models\Like;
use App\Models\PlayHistory;
use App\Models\SiteSetting;
use App\Models\Track;
use App\Support\ArtistActivityNotifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

            if ($track = Track::find($this->trackId)) {
                app(ArtistActivityNotifier::class)->sendTrackLikeMilestoneIfReached($track);
            }
        }

        $this->refreshLikeState();
    }

    public function downloadTrack(): void
    {
        if (! Auth::check()) {
            $this->dispatchDownloadLoginPrompt();

            return;
        }

        $track = Track::find($this->trackId);

        if (! $track) {
            return;
        }

        if (! $track->canBeDownloadedBy(Auth::user())) {
            $this->dispatch(
                'download-unavailable',
                message: $track->requires_donation
                    ? 'Unlock this track before downloading it.'
                    : 'This track is not available for download right now.'
            );

            return;
        }

        $this->dispatch(
            'start-download',
            url: route('track.download', $track),
            filename: $track->getDownloadFilename(),
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

                $this->trackId = $model->id;
                $this->track   = $trackData;
                $this->refreshLikeState();

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

    protected function dispatchDownloadLoginPrompt(): void
    {
        $track = $this->trackId ? Track::find($this->trackId) : null;

        $this->dispatch(
            'open-modal',
            id: 'login-required',
            title: 'Sign in to download',
            message: 'Log in or create a listener account to download this track.',
            loginUrl: $track ? route('track.download', $track) : route('login'),
            registerUrl: route('register', [
                'role' => 'listener',
                'show_role_guide' => 0,
            ]),
        );
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
            'artist_url' => $model->artistProfile
                ? route('artist.page', $model->artistProfile)
                : null,
            'cover' => $model->getCoverUrl(),
            'audio_url' => $audioUrl,
            'url' => route('track.show', $model->slug),
            'play_count' => (int) $model->play_count,
            'downloads_count' => (int) $model->downloads_count,
            'gift_intent_url' => route('donation.intent', $model),
            'duration' => $model->duration ?? 0,
            'requires_donation' => $model->requires_donation,
            'donation_amount' => $model->donation_amount,
            'can_gift_artist' => $model->canReceiveGifts(),
            'download_allowed' => $model->canBeDownloadedBy(Auth::user()),
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

    protected function buildAutoQueueTracks(Track $seedTrack): array
    {
        $totalLimit  = SiteSetting::autoQueueTotalLimit();
        $albumLimit  = SiteSetting::autoQueueAlbumLimit();
        $artistLimit = SiteSetting::autoQueueArtistLimit();
        $genreLimit  = SiteSetting::autoQueueGenreLimit();

        $seedId   = $seedTrack->id;
        $albumId  = $seedTrack->album_id;
        $artistId = $seedTrack->artist_profile_id;
        $genreId  = $seedTrack->genre_id;

        $orderCols  = 'is_featured DESC, play_count DESC, downloads_count DESC, created_at DESC';
        $subqueries = [];
        $bindings   = [];

        // Tier 1 – same album
        if ($albumId && $albumLimit > 0) {
            $subqueries[] = "(SELECT id, 1 AS tier, is_featured, play_count, downloads_count, created_at
                FROM tracks WHERE is_published = 1 AND album_id = ? AND id != ?
                ORDER BY {$orderCols} LIMIT ?)";
            $bindings = array_merge($bindings, [$albumId, $seedId, $albumLimit]);
        }

        // Tier 2 – same artist, excluding tracks already covered by the album tier
        if ($artistId && $artistLimit > 0) {
            $albumExclude = $albumId ? 'AND (album_id IS NULL OR album_id != ?)' : '';
            $subqueries[] = "(SELECT id, 2 AS tier, is_featured, play_count, downloads_count, created_at
                FROM tracks WHERE is_published = 1 AND artist_profile_id = ? {$albumExclude} AND id != ?
                ORDER BY {$orderCols} LIMIT ?)";
            $bindings = $albumId
                ? array_merge($bindings, [$artistId, $albumId, $seedId, $artistLimit])
                : array_merge($bindings, [$artistId, $seedId, $artistLimit]);
        }

        // Tier 3 – same genre, excluding tracks already covered by the artist tier
        if ($genreId && $genreLimit > 0) {
            $artistExclude = $artistId ? 'AND (artist_profile_id IS NULL OR artist_profile_id != ?)' : '';
            $subqueries[] = "(SELECT id, 3 AS tier, is_featured, play_count, downloads_count, created_at
                FROM tracks WHERE is_published = 1 AND genre_id = ? {$artistExclude} AND id != ?
                ORDER BY {$orderCols} LIMIT ?)";
            $bindings = $artistId
                ? array_merge($bindings, [$genreId, $artistId, $seedId, $genreLimit])
                : array_merge($bindings, [$genreId, $seedId, $genreLimit]);
        }

        // Tier 4 – global remainder, excluding everything already covered
        $globalWhereParts = ['id != ?'];
        $globalBindings   = [$seedId];
        if ($albumId) {
            $globalWhereParts[] = '(album_id IS NULL OR album_id != ?)';
            $globalBindings[]   = $albumId;
        }
        if ($artistId) {
            $globalWhereParts[] = '(artist_profile_id IS NULL OR artist_profile_id != ?)';
            $globalBindings[]   = $artistId;
        }
        if ($genreId) {
            $globalWhereParts[] = '(genre_id IS NULL OR genre_id != ?)';
            $globalBindings[]   = $genreId;
        }
        $globalBindings[] = $totalLimit;
        $globalWhere      = implode(' AND ', $globalWhereParts);
        $subqueries[]     = "(SELECT id, 4 AS tier, is_featured, play_count, downloads_count, created_at
            FROM tracks WHERE is_published = 1 AND {$globalWhere}
            ORDER BY {$orderCols} LIMIT ?)";
        $bindings         = array_merge($bindings, $globalBindings);

        // Single UNION ALL — outer query applies tier ordering and the total cap
        $union      = implode(' UNION ALL ', $subqueries);
        $sql        = "SELECT id FROM ({$union}) AS combined ORDER BY tier, {$orderCols} LIMIT ?";
        $bindings[] = $totalLimit;

        $orderedIds = array_column(DB::select($sql, $bindings), 'id');

        if (empty($orderedIds)) {
            return [];
        }

        // One eager-load pass, then restore the UNION ordering
        $tracks = Track::with(['artistProfile.user'])
            ->whereIn('id', $orderedIds)
            ->get()
            ->keyBy('id');

        return collect($orderedIds)
            ->map(fn ($id) => ($track = $tracks->get($id)) ? $this->makeQueueTrackPayload($track) : null)
            ->filter()
            ->values()
            ->all();
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
