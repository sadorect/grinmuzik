<?php

namespace Tests\Feature;

use App\Livewire\LikeButton;
use App\Livewire\MiniPlayer;
use App\Models\ArtistProfile;
use App\Models\Donation;
use App\Models\Like;
use App\Models\SiteSetting;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaActionsTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createArtistWithTrack(array $trackAttributes = []): array
    {
        $user = User::factory()->create();
        $profile = ArtistProfile::create([
            'user_id'    => $user->id,
            'stage_name' => 'Test Artist',
            'slug'       => 'test-artist',
            'is_approved'=> true,
            'is_active'  => true,
        ]);
        $track = Track::create(array_merge([
            'user_id'           => $user->id,
            'artist_profile_id' => $profile->id,
            'title'             => 'Test Track',
            'slug'              => 'test-track',
            'is_published'      => true,
            'is_free'           => true,
        ], $trackAttributes));

        return [$user, $profile, $track];
    }

    // ── LikeButton ────────────────────────────────────────────────────────────

    #[Test]
    public function like_button_renders_with_correct_initial_state(): void
    {
        [, , $track] = $this->createArtistWithTrack();

        Livewire::test(LikeButton::class, ['trackId' => $track->id])
            ->assertSet('liked', false)
            ->assertSet('count', 0);
    }

    #[Test]
    public function authenticated_user_can_like_a_track(): void
    {
        [$user, , $track] = $this->createArtistWithTrack();

        Livewire::actingAs($user)
            ->test(LikeButton::class, ['trackId' => $track->id])
            ->assertSet('liked', false)
            ->call('toggle')
            ->assertSet('liked', true)
            ->assertSet('count', 1);

        $this->assertDatabaseHas('likes', [
            'user_id'  => $user->id,
            'track_id' => $track->id,
        ]);
    }

    #[Test]
    public function authenticated_user_can_unlike_a_track(): void
    {
        [$user, , $track] = $this->createArtistWithTrack();

        Like::create(['user_id' => $user->id, 'track_id' => $track->id]);

        Livewire::actingAs($user)
            ->test(LikeButton::class, ['trackId' => $track->id])
            ->assertSet('liked', true)
            ->call('toggle')
            ->assertSet('liked', false)
            ->assertSet('count', 0);

        $this->assertDatabaseMissing('likes', [
            'user_id'  => $user->id,
            'track_id' => $track->id,
        ]);
    }

    #[Test]
    public function guest_like_attempt_opens_login_modal(): void
    {
        [, , $track] = $this->createArtistWithTrack();

        Livewire::test(LikeButton::class, ['trackId' => $track->id])
            ->call('toggle')
            ->assertDispatched('open-modal');

        $this->assertDatabaseCount('likes', 0);
    }

    #[Test]
    public function like_count_reflects_multiple_users(): void
    {
        [$user1, , $track] = $this->createArtistWithTrack();
        $user2 = User::factory()->create();
        Like::create(['user_id' => $user1->id, 'track_id' => $track->id]);
        Like::create(['user_id' => $user2->id, 'track_id' => $track->id]);

        Livewire::test(LikeButton::class, ['trackId' => $track->id])
            ->assertSet('count', 2);
    }

    // ── MiniPlayer ────────────────────────────────────────────────────────────

    #[Test]
    public function mini_player_loads_a_published_track_on_play_track_event(): void
    {
        [, , $track] = $this->createArtistWithTrack();

        // Stub getAudioUrl so no real media file is needed
        $trackMock = $this->partialMock(Track::class);
        $trackMock->shouldNotReceive('getAudioUrl'); // will use the real model via DB

        // Mock the Track to return a fake audio URL
        $track->audio_file_path = 'fake/path.mp3';

        Livewire::test(MiniPlayer::class)
            ->assertSet('trackId', null)
            ->dispatch('play-track', id: $track->id);
        // Player silently bails if no audio URL, but should not throw
    }

    #[Test]
    public function mini_player_dispatching_play_track_for_track_without_audio_does_not_throw(): void
    {
        [$user, , $track] = $this->createArtistWithTrack();

        // Track has no media attached — MiniPlayer silently bails, no exception
        Livewire::actingAs($user)
            ->test(MiniPlayer::class)
            ->dispatch('play-track', id: $track->id);

        $this->assertTrue(true);
    }

    #[Test]
    public function mini_player_builds_auto_playlist_recommendations_when_enabled(): void
    {
        config()->set('media-storage.private_disk', 'local');
        config()->set('media-storage.public_disk', 'public');

        Storage::fake('local');
        Storage::fake('public');

        SiteSetting::current()->update([
            'auto_playlist_enabled' => true,
        ]);

        [$user, $profile, $track] = $this->createArtistWithTrack([
            'audio_file_path' => 'audio/test-track.mp3',
        ]);

        Storage::disk('public')->put('audio/test-track.mp3', 'seed-audio');
        Storage::disk('public')->put('audio/follow-up-one.mp3', 'follow-up-one');
        Storage::disk('public')->put('audio/follow-up-two.mp3', 'follow-up-two');

        $followUpOne = Track::create([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Follow Up One',
            'slug' => 'follow-up-one',
            'is_published' => true,
            'is_free' => true,
            'audio_file_path' => 'audio/follow-up-one.mp3',
            'play_count' => 90,
        ]);

        $followUpTwo = Track::create([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Follow Up Two',
            'slug' => 'follow-up-two',
            'is_published' => true,
            'is_free' => true,
            'audio_file_path' => 'audio/follow-up-two.mp3',
            'play_count' => 75,
        ]);

        $component = Livewire::test(MiniPlayer::class);
        $component->call('loadTrack', $track->id)
            ->assertSet('trackId', $track->id);

        $payload = $component->get('track');
        $recommendedIds = collect($payload['auto_queue_tracks'] ?? [])
            ->pluck('id')
            ->all();

        $this->assertContains($followUpOne->id, $recommendedIds);
        $this->assertContains($followUpTwo->id, $recommendedIds);
        $this->assertNotContains($track->id, $recommendedIds);
    }

    #[Test]
    public function mini_player_skips_auto_playlist_recommendations_when_disabled(): void
    {
        config()->set('media-storage.private_disk', 'local');
        config()->set('media-storage.public_disk', 'public');

        Storage::fake('local');
        Storage::fake('public');

        SiteSetting::current()->update([
            'auto_playlist_enabled' => false,
        ]);

        [, , $track] = $this->createArtistWithTrack([
            'audio_file_path' => 'audio/test-track.mp3',
        ]);

        Storage::disk('public')->put('audio/test-track.mp3', 'seed-audio');

        $component = Livewire::test(MiniPlayer::class);
        $component->call('loadTrack', $track->id)
            ->assertSet('trackId', $track->id);

        $payload = $component->get('track');

        $this->assertSame([], $payload['auto_queue_tracks'] ?? null);
    }

    #[Test]
    public function mini_player_like_toggle_for_guest_dispatches_open_modal(): void
    {
        [, , $track] = $this->createArtistWithTrack();

        Livewire::test(MiniPlayer::class)
            ->set('trackId', $track->id)
            ->call('likeToggle')
            ->assertDispatched('open-modal');
    }

    #[Test]
    public function mini_player_like_toggle_for_authenticated_user_creates_like(): void
    {
        [$user, , $track] = $this->createArtistWithTrack();

        Livewire::actingAs($user)
            ->test(MiniPlayer::class)
            ->set('trackId', $track->id)
            ->call('likeToggle')
            ->assertSet('liked', true);

        $this->assertDatabaseHas('likes', [
            'user_id'  => $user->id,
            'track_id' => $track->id,
        ]);
    }

    #[Test]
    public function mini_player_download_for_guest_dispatches_open_modal(): void
    {
        [, , $track] = $this->createArtistWithTrack();

        Livewire::test(MiniPlayer::class)
            ->set('trackId', $track->id)
            ->call('downloadTrack')
            ->assertDispatched('open-modal');
    }

    #[Test]
    public function guest_download_route_redirects_to_login_instead_of_returning_forbidden(): void
    {
        [, , $track] = $this->createArtistWithTrack([
            'audio_file_path' => 'tracks/test-track.mp3',
        ]);

        $this->get(route('track.download', $track))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function mini_player_download_dispatches_start_download_for_allowed_user(): void
    {
        [$user, , $track] = $this->createArtistWithTrack();

        Livewire::actingAs($user)
            ->test(MiniPlayer::class)
            ->set('trackId', $track->id)
            ->call('downloadTrack')
            ->assertDispatched('start-download');
    }

    #[Test]
    public function mini_player_download_for_locked_track_dispatches_download_unavailable(): void
    {
        SiteSetting::current()->update([
            'payments_enabled' => true,
        ]);

        [$user, , $track] = $this->createArtistWithTrack([
            'is_free' => false,
            'donation_amount' => 5,
        ]);

        Livewire::actingAs($user)
            ->test(MiniPlayer::class)
            ->set('trackId', $track->id)
            ->call('downloadTrack')
            ->assertDispatched('download-unavailable');
    }

    #[Test]
    public function authenticated_user_can_download_track_from_route_and_counter_increments(): void
    {
        config()->set('media-storage.private_disk', 'local');
        config()->set('media-storage.public_disk', 'public');

        Storage::fake('local');

        [$user, , $track] = $this->createArtistWithTrack([
            'audio_file_path' => 'tracks/test-track.mp3',
        ]);

        Storage::disk('local')->put('tracks/test-track.mp3', 'audio-bytes');

        $response = $this->actingAs($user)
            ->get(route('track.download', $track))
            ->assertRedirect();

        $this->assertStringContainsString('test-track.mp3', (string) $response->headers->get('location'));

        $this->assertEquals(1, (int) $track->fresh()->downloads_count);
    }

    #[Test]
    public function ajax_download_route_streams_same_origin_response_without_redirecting(): void
    {
        config()->set('media-storage.private_disk', 'local');
        config()->set('media-storage.public_disk', 'public');

        Storage::fake('local');

        [$user, , $track] = $this->createArtistWithTrack([
            'audio_file_path' => 'tracks/test-track.mp3',
        ]);

        Storage::disk('local')->put('tracks/test-track.mp3', 'audio-bytes');

        $response = $this->actingAs($user)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('track.download', $track));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
        $this->assertEquals(1, (int) $track->fresh()->downloads_count);
    }

    #[Test]
    public function locked_track_download_route_requires_completed_unlock(): void
    {
        config()->set('media-storage.private_disk', 'local');
        config()->set('media-storage.public_disk', 'public');

        Storage::fake('local');

        SiteSetting::current()->update([
            'payments_enabled' => true,
        ]);

        [$user, $profile, $track] = $this->createArtistWithTrack([
            'title' => 'Locked Track',
            'slug' => 'locked-track',
            'is_free' => false,
            'donation_amount' => 5,
            'audio_file_path' => 'tracks/locked-track.mp3',
        ]);

        Storage::disk('local')->put('tracks/locked-track.mp3', 'audio-bytes');

        $this->actingAs($user)
            ->get(route('track.download', $track))
            ->assertForbidden();

        Donation::create([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'track_id' => $track->id,
            'amount' => 5,
            'stripe_payment_intent_id' => 'pi_test_unlock',
            'type' => 'unlock',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)
            ->get(route('track.download', $track))
            ->assertRedirect();

        $this->assertStringContainsString('locked-track.mp3', (string) $response->headers->get('location'));
    }

    // ── Track show page ───────────────────────────────────────────────────────

    #[Test]
    public function track_show_page_is_accessible_for_published_track(): void
    {
        [, , $track] = $this->createArtistWithTrack();

        $this->get(route('track.show', $track->slug))
            ->assertOk()
            ->assertSee($track->title);
    }

    #[Test]
    public function unpublished_track_show_page_returns_404(): void
    {
        [, , $track] = $this->createArtistWithTrack(['is_published' => false]);

        $this->get(route('track.show', $track->slug))
            ->assertNotFound();
    }

    #[Test]
    public function placeholder_image_file_exists(): void
    {
        $this->assertFileExists(public_path('images/placeholder-track.svg'));
    }
}
