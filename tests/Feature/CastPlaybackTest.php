<?php

namespace Tests\Feature;

use App\Livewire\MiniPlayer;
use App\Models\ArtistProfile;
use App\Models\Track;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Server side of Chromecast / AirPlay: signed audio URLs must outlive the
 * song (the TV fetches them itself), the player can ask for a fresh one, and
 * the CSP lets the Cast sender SDK load.
 */
class CastPlaybackTest extends TestCase
{
    use RefreshDatabase;

    private function createTrack(array $attributes = []): Track
    {
        $user = User::factory()->create();
        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Cast Artist',
            'slug' => 'cast-artist-' . $user->id,
            'is_approved' => true,
            'is_active' => true,
        ]);

        return Track::create(array_merge([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Cast Track',
            'slug' => 'cast-track',
            'is_published' => true,
            'is_free' => true,
            'audio_file_path' => 'audio/cast-track.mp3',
        ], $attributes));
    }

    /**
     * Private disk = a faked local disk whose temporary URLs record the
     * expiry they were signed with.
     */
    private function fakePrivateDisk(): void
    {
        config()->set('media-storage.private_disk', 'local');
        config()->set('media-storage.private_url_ttl_minutes', 15);

        Storage::fake('local');
        Storage::disk('local')->put('audio/cast-track.mp3', 'audio');
        Storage::disk('local')->buildTemporaryUrlsUsing(
            fn (string $path, DateTimeInterface $expiration) => 'https://signed.test/' . $path . '?expires=' . $expiration->getTimestamp()
        );
    }

    #[Test]
    public function audio_url_expiry_covers_the_track_length_plus_the_ttl(): void
    {
        $this->freezeTime();
        config()->set('media-storage.private_url_ttl_minutes', 15);

        // 21m10s rounds up to 22 minutes, plus the 15 minute TTL.
        $long = $this->createTrack(['duration' => 1270]);
        $this->assertTrue($long->audioUrlExpiry()->equalTo(now()->addMinutes(37)));

        // No known duration: the plain TTL, as before.
        $unknown = $this->createTrack(['slug' => 'unknown-length', 'duration' => 0]);
        $this->assertTrue($unknown->audioUrlExpiry()->equalTo(now()->addMinutes(15)));
    }

    #[Test]
    public function private_audio_urls_are_signed_for_the_whole_song(): void
    {
        $this->freezeTime();
        $this->fakePrivateDisk();

        $track = $this->createTrack(['duration' => 1270]);

        $this->assertSame(
            'https://signed.test/audio/cast-track.mp3?expires=' . now()->addMinutes(37)->getTimestamp(),
            $track->getAudioUrl(),
        );
    }

    #[Test]
    public function mini_player_hands_out_a_fresh_audio_url_for_a_published_track(): void
    {
        $this->fakePrivateDisk();
        $track = $this->createTrack(['duration' => 200]);

        Livewire::test(MiniPlayer::class)
            ->call('freshAudioUrl', $track->id)
            ->assertReturned(fn ($url) => is_string($url) && str_starts_with($url, 'https://signed.test/audio/cast-track.mp3'));
    }

    #[Test]
    public function mini_player_refuses_a_fresh_audio_url_for_unpublished_or_missing_tracks(): void
    {
        $this->fakePrivateDisk();
        $draft = $this->createTrack(['is_published' => false]);

        Livewire::test(MiniPlayer::class)
            ->call('freshAudioUrl', $draft->id)
            ->assertReturned(null)
            ->call('freshAudioUrl', 999999)
            ->assertReturned(null);
    }

    #[Test]
    public function csp_allows_the_google_cast_sender_sdk(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression('/script-src [^;]*https:\/\/www\.gstatic\.com/', (string) $csp);
    }
}
