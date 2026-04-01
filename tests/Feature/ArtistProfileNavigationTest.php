<?php

namespace Tests\Feature;

use App\Models\ArtistProfile;
use App\Models\PlayHistory;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArtistProfileNavigationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function charts_page_renders_artist_name_links_to_public_profiles(): void
    {
        Cache::flush();

        [$user, $profile, $track] = $this->createArtistWithTrack([
            'title' => 'Chart Track',
            'slug' => 'chart-track',
            'play_count' => 120,
            'downloads_count' => 18,
        ]);

        PlayHistory::create([
            'user_id' => $user->id,
            'track_id' => $track->id,
            'ip_address' => '127.0.0.1',
            'source' => 'web',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $response = $this->get(route('charts', ['timeframe' => 'all']))
            ->assertOk()
            ->assertSee($track->title);

        $this->assertArtistProfileLinkUsesWireNavigate($response, $profile);
    }

    #[Test]
    public function track_show_artist_link_uses_livewire_navigation(): void
    {
        Cache::flush();

        [, $profile, $track] = $this->createArtistWithTrack([
            'title' => 'Track Show Link',
            'slug' => 'track-show-link',
        ]);

        $response = $this->get(route('track.show', $track->slug))
            ->assertOk()
            ->assertSee($track->title);

        $this->assertArtistProfileLinkUsesWireNavigate($response, $profile);
    }

    #[Test]
    public function new_releases_artist_links_use_livewire_navigation(): void
    {
        Cache::flush();

        [, $profile, $track] = $this->createArtistWithTrack([
            'title' => 'Fresh Drop',
            'slug' => 'fresh-drop',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('new-releases'))
            ->assertOk()
            ->assertSee($track->title);

        $this->assertArtistProfileLinkUsesWireNavigate($response, $profile);
    }

    private function createArtistWithTrack(array $trackAttributes = []): array
    {
        $user = User::factory()->create([
            'name' => 'Navigation Artist User',
        ]);

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Navigation Artist',
            'slug' => 'navigation-artist',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $track = Track::create(array_merge([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Navigation Track',
            'slug' => 'navigation-track',
            'is_published' => true,
            'is_free' => true,
        ], $trackAttributes));

        return [$user, $profile, $track];
    }

    private function assertArtistProfileLinkUsesWireNavigate(TestResponse $response, ArtistProfile $profile): void
    {
        $html = (string) $response->getContent();
        $url = preg_quote(route('artist.page', $profile), '/');
        $label = preg_quote($profile->stage_name ?? $profile->user?->name ?? '', '/');

        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="' . $url . '"[^>]*wire:navigate[^>]*>\s*' . $label . '\s*<\/a>/s',
            $html,
        );
    }
}
