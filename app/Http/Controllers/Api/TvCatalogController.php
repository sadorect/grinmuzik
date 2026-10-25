<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\ArtistProfile;
use App\Models\ChartSnapshot;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use App\Support\Tv\TvPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * What the TV app browses. Everything works signed out, as the website does;
 * a signed-in TV also gets its listener's own rows on the home screen.
 */
class TvCatalogController extends Controller
{
    private const ROW_SIZE = 20;

    public function home(Request $request): JsonResponse
    {
        // The public rows are the same for every TV, so they are built once
        // every few minutes rather than per request.
        $rows = Cache::remember('tv:home:public', now()->addMinutes(5), fn () => $this->publicRows());

        if ($user = $this->listener($request)) {
            $rows = array_merge($this->personalRows($user), $rows);
        }

        return response()->json([
            'rows' => array_values(array_filter($rows, fn (array $row) => $row['items'] !== [])),
        ]);
    }

    public function album(Album $album): JsonResponse
    {
        abort_unless($album->is_published, 404);

        $album->load(['artist', 'media'])->loadCount('publicTracks as public_tracks_count');

        $tracks = $album->publicTracks()->with(TvPayload::TRACK_WITH)->get();

        return response()->json([
            'album' => TvPayload::album($album),
            'tracks' => $this->tracks($tracks),
        ]);
    }

    public function artist(ArtistProfile $artist): JsonResponse
    {
        abort_unless($artist->is_approved && $artist->is_active, 404);

        $tracks = $artist->tracks()
            ->published()
            ->with(TvPayload::TRACK_WITH)
            ->orderByDesc('play_count')
            ->limit(self::ROW_SIZE)
            ->get();

        $albums = Album::publiclyVisible()
            ->where('artist_profile_id', $artist->id)
            ->withPublicTrackCount()
            ->with(['artist', 'media'])
            ->orderByDesc('release_date')
            ->get();

        return response()->json([
            'artist' => TvPayload::artist($artist->load(['media', 'user.media'])),
            'tracks' => $this->tracks($tracks),
            'albums' => $albums->map(fn (Album $album) => TvPayload::album($album))->all(),
        ]);
    }

    public function playlist(Request $request, Playlist $playlist): JsonResponse
    {
        $user = $this->listener($request);

        // A private playlist exists only for its owner; anyone else gets the
        // same 404 as a playlist that was never made.
        abort_unless($playlist->is_public || ($user && $playlist->user_id === $user->id), 404);

        $tracks = $playlist->tracks()->published()->with(TvPayload::TRACK_WITH)->get();

        $playlist->load(['user', 'media'])->loadCount('tracks');

        return response()->json([
            'playlist' => TvPayload::playlist($playlist),
            'tracks' => $this->tracks($tracks),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));

        if ($query === '') {
            return response()->json(['tracks' => [], 'artists' => [], 'albums' => []]);
        }

        $like = '%'.addcslashes($query, '%_\\').'%';

        $tracks = Track::published()
            ->where('title', 'LIKE', $like)
            ->with(TvPayload::TRACK_WITH)
            ->orderByDesc('play_count')
            ->limit(self::ROW_SIZE)
            ->get();

        $artists = ArtistProfile::publiclyDiscoverable()
            ->where('stage_name', 'LIKE', $like)
            ->with(['media', 'user.media'])
            ->orderByDesc('total_plays')
            ->limit(self::ROW_SIZE)
            ->get();

        $albums = Album::publiclyVisible()
            ->where('title', 'LIKE', $like)
            ->withPublicTrackCount()
            ->with(['artist', 'media'])
            ->orderByDesc('release_date')
            ->limit(self::ROW_SIZE)
            ->get();

        return response()->json([
            'tracks' => $this->tracks($tracks),
            'artists' => $artists->map(fn (ArtistProfile $artist) => TvPayload::artist($artist))->all(),
            'albums' => $albums->map(fn (Album $album) => TvPayload::album($album))->all(),
        ]);
    }

    private function publicRows(): array
    {
        $featured = Track::published()
            ->where('is_featured', true)
            ->with(TvPayload::TRACK_WITH)
            ->orderByDesc('play_count')
            ->limit(self::ROW_SIZE)
            ->get();

        $newReleases = Track::published()
            ->with(TvPayload::TRACK_WITH)
            ->latest()
            ->limit(self::ROW_SIZE)
            ->get();

        $albums = Album::publiclyVisible()
            ->withPublicTrackCount()
            ->with(['artist', 'media'])
            ->orderByDesc('release_date')
            ->orderByDesc('created_at')
            ->limit(self::ROW_SIZE)
            ->get();

        $artists = ArtistProfile::publiclyDiscoverable()
            ->with(['media', 'user.media'])
            ->orderByDesc('total_plays')
            ->limit(self::ROW_SIZE)
            ->get();

        $playlists = Playlist::public()
            ->has('tracks')
            ->withCount('tracks')
            ->with(['user', 'media'])
            ->latest()
            ->limit(self::ROW_SIZE)
            ->get();

        return [
            $this->row('featured', 'Featured', 'track', $this->tracks($featured)),
            $this->row('trending', 'Trending now', 'track', $this->tracks($this->trendingTracks())),
            $this->row('chart', $this->chartTitle(), 'track', $this->tracks($this->chartTracks())),
            $this->row('new', 'New releases', 'track', $this->tracks($newReleases)),
            $this->row('albums', 'Albums & EPs', 'album', $albums->map(fn (Album $album) => TvPayload::album($album))->all()),
            $this->row('artists', 'Artists', 'artist', $artists->map(fn (ArtistProfile $artist) => TvPayload::artist($artist))->all()),
            $this->row('playlists', 'Playlists', 'playlist', $playlists->map(fn (Playlist $playlist) => TvPayload::playlist($playlist))->all()),
        ];
    }

    private function personalRows(User $user): array
    {
        $recentIds = DB::table('play_histories')
            ->select('track_id', DB::raw('max(created_at) as last_played'))
            ->where('user_id', $user->id)
            ->groupBy('track_id')
            ->orderByDesc('last_played')
            ->limit(self::ROW_SIZE)
            ->pluck('track_id');

        $liked = $user->likes()
            ->published()
            ->with(TvPayload::TRACK_WITH)
            ->orderByPivot('created_at', 'desc')
            ->limit(self::ROW_SIZE)
            ->get();

        $playlists = $user->playlists()
            ->withCount('tracks')
            ->with(['user', 'media'])
            ->latest()
            ->limit(self::ROW_SIZE)
            ->get();

        return [
            $this->row('recent', 'Recently played', 'track', $this->tracks($this->publishedInOrder($recentIds))),
            $this->row('liked', 'Liked songs', 'track', $this->tracks($liked)),
            $this->row('my-playlists', 'Your playlists', 'playlist', $playlists->map(fn (Playlist $playlist) => TvPayload::playlist($playlist))->all()),
        ];
    }

    /** Most plays in the last 48 hours, as on the trending API. */
    private function trendingTracks(): Collection
    {
        $ids = DB::table('play_histories')
            ->select('track_id', DB::raw('count(*) as plays'))
            ->where('created_at', '>=', now()->subHours(48))
            ->groupBy('track_id')
            ->orderByDesc('plays')
            ->limit(self::ROW_SIZE)
            ->pluck('track_id');

        return $this->publishedInOrder($ids);
    }

    private function chartTracks(): Collection
    {
        $snapshot = $this->latestWeeklyChart();

        if (! $snapshot) {
            return collect();
        }

        $ids = $snapshot->entries()->limit(self::ROW_SIZE)->pluck('track_id');

        return $this->publishedInOrder($ids);
    }

    private function chartTitle(): string
    {
        return $this->latestWeeklyChart()?->display_title ?: 'Weekly chart';
    }

    private function latestWeeklyChart(): ?ChartSnapshot
    {
        return ChartSnapshot::where('period_type', 'week')->latest('generated_at')->first();
    }

    /** Published tracks for these ids, in the order the ids came. */
    private function publishedInOrder(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $tracks = Track::published()
            ->whereIn('id', $ids)
            ->with(TvPayload::TRACK_WITH)
            ->get()
            ->keyBy('id');

        return $ids->map(fn ($id) => $tracks->get($id))->filter()->values();
    }

    private function tracks(Collection $tracks): array
    {
        return $tracks->map(fn (Track $track) => TvPayload::track($track))->values()->all();
    }

    private function row(string $key, string $title, string $kind, array $items): array
    {
        return ['key' => $key, 'title' => $title, 'kind' => $kind, 'items' => $items];
    }

    /**
     * The signed-in listener, when the TV sent a token. These routes are
     * public, so a missing or bad token just means "signed out".
     */
    private function listener(Request $request): ?User
    {
        $user = $request->user('sanctum');

        return $user && ($user->tokenCan('listen') || $user->tokenCan('write')) ? $user : null;
    }
}
