<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\ArtistProfile;
use App\Models\Like;
use App\Models\PlayHistory;
use App\Models\Playlist;
use App\Models\SiteSetting;
use App\Models\Track;
use App\Models\TvDeviceCode;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The TV app's browse and playback API: open to signed-out TVs like the
 * website, with personal rows for a signed-in one, and never showing
 * anything the website would hide.
 */
class TvCatalogTest extends TestCase
{
    use RefreshDatabase;

    private ArtistProfile $artist;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();
        $this->artist = ArtistProfile::create([
            'user_id' => $owner->id,
            'stage_name' => 'Ada Sings',
            'slug' => 'ada-sings',
            'is_approved' => true,
            'is_active' => true,
        ]);
    }

    private function track(string $slug, array $attributes = []): Track
    {
        return Track::create(array_merge([
            'user_id' => $this->artist->user_id,
            'artist_profile_id' => $this->artist->id,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'duration' => 200,
            'is_published' => true,
            'audio_file_path' => 'audio/'.$slug.'.mp3',
        ], $attributes));
    }

    private function album(string $slug, array $attributes = []): Album
    {
        return Album::create(array_merge([
            'user_id' => $this->artist->user_id,
            'artist_profile_id' => $this->artist->id,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'type' => 'album',
            'is_published' => true,
            'release_date' => '2026-05-01',
        ], $attributes));
    }

    private function listenToken(User $user): string
    {
        return $user->createToken('tv:Test', [TvDeviceCode::TOKEN_ABILITY])->plainTextToken;
    }

    private function rows($response): array
    {
        return collect($response->json('rows'))->keyBy('key')->all();
    }

    private function fakePrivateAudio(): void
    {
        config()->set('media-storage.private_disk', 'local');
        Storage::fake('local');
        Storage::disk('local')->put('audio/seed-song.mp3', 'audio');
        Storage::disk('local')->buildTemporaryUrlsUsing(
            fn (string $path, DateTimeInterface $expiration) => 'https://signed.test/'.$path
        );
    }

    #[Test]
    public function a_signed_out_tv_gets_public_rows_without_hidden_music(): void
    {
        $this->track('open-road', ['is_featured' => true]);
        $this->track('draft-song', ['is_published' => false, 'is_featured' => true]);
        $this->album('first-light')->tracks()->save($this->track('first-light-intro'));
        $this->album('unreleased', ['is_published' => false]);

        $rows = $this->rows($this->getJson('/api/tv/home')->assertOk());

        $this->assertSame(['featured', 'new', 'albums', 'artists'], array_keys($rows));
        $this->assertSame(['open-road'], array_column($rows['featured']['items'], 'slug'));
        $this->assertNotContains('draft-song', array_column($rows['new']['items'], 'slug'));
        $this->assertSame(['first-light'], array_column($rows['albums']['items'], 'slug'));
        $this->assertSame('Ada Sings', $rows['artists']['items'][0]['name']);

        $item = $rows['featured']['items'][0];
        $this->assertSame(['slug' => 'ada-sings', 'name' => 'Ada Sings'], $item['artist']);
        $this->assertSame(200, $item['duration']);
        $this->assertSame(SiteSetting::defaultImageUrl(), $item['artwork_url']);
        $this->assertSame('track', $rows['featured']['kind']);
    }

    #[Test]
    public function a_signed_in_tv_also_gets_its_listeners_rows_first(): void
    {
        $user = User::factory()->create();
        $played = $this->track('played-yesterday');
        $liked = $this->track('liked-song');
        PlayHistory::create(['user_id' => $user->id, 'track_id' => $played->id, 'seconds_played' => 60, 'source' => 'web']);
        Like::create(['user_id' => $user->id, 'track_id' => $liked->id]);
        $private = Playlist::create(['user_id' => $user->id, 'name' => 'Sunday mornings', 'is_public' => false]);
        $private->tracks()->attach($liked->id, ['position' => 1]);

        // Someone else's listening must not leak into this TV.
        $other = User::factory()->create();
        Like::create(['user_id' => $other->id, 'track_id' => $played->id]);

        $rows = $this->rows($this->withToken($this->listenToken($user))->getJson('/api/tv/home')->assertOk());

        $this->assertSame(['recent', 'liked', 'my-playlists'], array_slice(array_keys($rows), 0, 3));
        $this->assertSame(['played-yesterday'], array_column($rows['recent']['items'], 'slug'));
        $this->assertSame(['liked-song'], array_column($rows['liked']['items'], 'slug'));
        $this->assertSame('Sunday mornings', $rows['my-playlists']['items'][0]['name']);
        $this->assertArrayNotHasKey('playlists', $rows, 'A private playlist must not appear in the public row.');
    }

    #[Test]
    public function a_bad_token_just_means_signed_out(): void
    {
        $this->track('open-road');

        $rows = $this->rows($this->withToken('1|not-a-real-token')->getJson('/api/tv/home')->assertOk());

        $this->assertArrayNotHasKey('recent', $rows);
        $this->assertArrayHasKey('new', $rows);
    }

    #[Test]
    public function playback_gives_a_signed_url_that_outlives_the_song(): void
    {
        $this->fakePrivateAudio();
        SiteSetting::current()->update(['auto_playlist_enabled' => false]);
        $this->track('seed-song');

        $response = $this->getJson('/api/tv/tracks/seed-song/play')->assertOk();

        $this->assertSame('https://signed.test/audio/seed-song.mp3', $response->json('audio_url'));
        $this->assertSame('seed-song', $response->json('track.slug'));
        $this->assertNotNull($response->json('audio_url_expires_at'));
        $this->assertSame(SiteSetting::playCountMinSeconds(), $response->json('play_count_min_seconds'));
        $this->assertSame([], $response->json('up_next'));
    }

    #[Test]
    public function playback_queues_up_next_tracks_without_urls(): void
    {
        // AutoQueue's UNION of parenthesised, LIMITed SELECTs is MySQL-only;
        // see MediaActionsTest::mini_player_builds_auto_playlist_recommendations_when_enabled.
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('Auto-queue uses MySQL-only UNION syntax; needs a MySQL connection to exercise.');
        }

        $this->fakePrivateAudio();
        SiteSetting::current()->update(['auto_playlist_enabled' => true]);
        $this->track('seed-song');
        $this->track('next-song');
        $this->track('hidden-song', ['is_published' => false]);

        $response = $this->getJson('/api/tv/tracks/seed-song/play')->assertOk();

        $upNext = array_column($response->json('up_next'), 'slug');
        $this->assertContains('next-song', $upNext);
        $this->assertNotContains('seed-song', $upNext);
        $this->assertNotContains('hidden-song', $upNext);
        $this->assertArrayNotHasKey('audio_url', $response->json('up_next.0'));
    }

    #[Test]
    public function an_unpublished_track_cannot_be_played(): void
    {
        $this->fakePrivateAudio();
        $this->track('draft-song', ['is_published' => false]);

        $this->getJson('/api/tv/tracks/draft-song/play')->assertNotFound();
        $this->postJson('/api/tv/tracks/draft-song/play', ['seconds_played' => 60])->assertNotFound();
    }

    #[Test]
    public function a_signed_out_play_counts_without_history(): void
    {
        $track = $this->track('open-road');

        $this->postJson('/api/tv/tracks/open-road/play', ['seconds_played' => 5])
            ->assertOk()->assertJson(['counted' => false]);
        $this->postJson('/api/tv/tracks/open-road/play', ['seconds_played' => 60])
            ->assertOk()->assertJson(['counted' => true]);

        $this->assertSame(1, $track->fresh()->play_count);
        $this->assertSame(0, PlayHistory::count());
    }

    #[Test]
    public function a_signed_in_play_lands_in_history_as_a_tv_play(): void
    {
        $user = User::factory()->create();
        $track = $this->track('open-road');

        $this->withToken($this->listenToken($user))
            ->postJson('/api/tv/tracks/open-road/play', ['seconds_played' => 200, 'completed' => true])
            ->assertOk()->assertJson(['counted' => true]);

        $this->assertDatabaseHas('play_histories', ['user_id' => $user->id, 'track_id' => $track->id, 'source' => 'tv']);
    }

    #[Test]
    public function album_pages_show_only_published_tracks_and_hide_unpublished_albums(): void
    {
        $album = $this->album('first-light');
        $album->tracks()->save($this->track('intro', ['track_number' => 1]));
        $album->tracks()->save($this->track('outro-demo', ['track_number' => 2, 'is_published' => false]));
        $this->album('unreleased', ['is_published' => false]);

        $response = $this->getJson('/api/tv/albums/first-light')->assertOk();
        $this->assertSame(['intro'], array_column($response->json('tracks'), 'slug'));
        $this->assertSame(1, $response->json('album.track_count'));
        $this->assertSame(2026, $response->json('album.release_year'));

        $this->getJson('/api/tv/albums/unreleased')->assertNotFound();
    }

    #[Test]
    public function artist_pages_list_tracks_and_albums_and_hide_unapproved_artists(): void
    {
        $this->track('hit-song', ['play_count' => 50]);
        $this->track('deep-cut', ['play_count' => 2]);
        $this->album('first-light');

        $response = $this->getJson('/api/tv/artists/ada-sings')->assertOk();
        $this->assertSame('Ada Sings', $response->json('artist.name'));
        $this->assertSame(['hit-song', 'deep-cut'], array_column($response->json('tracks'), 'slug'));
        $this->assertSame(['first-light'], array_column($response->json('albums'), 'slug'));

        $this->artist->update(['is_approved' => false]);
        $this->getJson('/api/tv/artists/ada-sings')->assertNotFound();
    }

    #[Test]
    public function a_private_playlist_is_only_visible_to_its_owner(): void
    {
        $owner = User::factory()->create();
        $playlist = Playlist::create(['user_id' => $owner->id, 'name' => 'Mine', 'is_public' => false]);
        $playlist->tracks()->attach($this->track('kept')->id, ['position' => 1]);
        $playlist->tracks()->attach($this->track('pulled', ['is_published' => false])->id, ['position' => 2]);

        $this->getJson("/api/tv/playlists/{$playlist->slug}")->assertNotFound();
        $this->withToken($this->listenToken(User::factory()->create()))
            ->getJson("/api/tv/playlists/{$playlist->slug}")->assertNotFound();

        $this->app['auth']->forgetGuards();
        $response = $this->withToken($this->listenToken($owner))
            ->getJson("/api/tv/playlists/{$playlist->slug}")
            ->assertOk();
        $this->assertSame(['kept'], array_column($response->json('tracks'), 'slug'));
    }

    #[Test]
    public function search_finds_tracks_artists_and_albums(): void
    {
        $this->track('sunrise-song');
        $this->track('sunrise-draft', ['is_published' => false]);
        $this->album('sunrise-sessions');

        $response = $this->getJson('/api/tv/search?q=sunrise')->assertOk();
        $this->assertSame(['sunrise-song'], array_column($response->json('tracks'), 'slug'));
        $this->assertSame(['sunrise-sessions'], array_column($response->json('albums'), 'slug'));

        $this->assertSame(['ada-sings'], array_column($this->getJson('/api/tv/search?q=ada')->json('artists'), 'slug'));
        $this->getJson('/api/tv/search?q=')->assertOk()->assertExactJson(['tracks' => [], 'artists' => [], 'albums' => []]);
    }

    #[Test]
    public function a_packaged_tv_app_may_call_the_api_cross_origin(): void
    {
        // A packaged TV app runs from local files, so its requests carry a
        // foreign (or "null") origin and a bearer token, which needs a preflight.
        $this->call('OPTIONS', '/api/tv/home', [], [], [], [
            'HTTP_ORIGIN' => 'null',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*');

        $this->getJson('/api/tv/home', ['Origin' => 'null'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }
}
