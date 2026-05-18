<?php

namespace Tests\Feature;

use App\Models\ArtistProfile;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistDiscoverabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_discoverability_requires_an_approved_active_artist_with_a_published_track(): void
    {
        [$discoverableProfile] = $this->createArtistProfileWithTrack([
            'slug' => 'discoverable-artist',
        ], [
            'slug' => 'discoverable-track',
            'is_published' => true,
        ]);

        [$draftOnlyProfile] = $this->createArtistProfileWithTrack([
            'slug' => 'draft-only-artist',
        ], [
            'slug' => 'draft-only-track',
            'is_published' => false,
        ]);

        $discoverableIds = ArtistProfile::query()
            ->publiclyDiscoverable()
            ->pluck('id');

        $this->assertTrue($discoverableIds->contains($discoverableProfile->id));
        $this->assertFalse($discoverableIds->contains($draftOnlyProfile->id));
    }

    public function test_artist_falls_back_out_of_discoverability_after_last_published_track_is_unpublished(): void
    {
        [$profile, $track] = $this->createArtistProfileWithTrack([
            'slug' => 'limbo-artist',
        ], [
            'slug' => 'limbo-track',
            'is_published' => true,
        ]);

        $this->assertTrue(
            ArtistProfile::query()->publiclyDiscoverable()->whereKey($profile->id)->exists()
        );

        $track->update([
            'is_published' => false,
        ]);

        $this->assertFalse(
            ArtistProfile::query()->publiclyDiscoverable()->whereKey($profile->id)->exists()
        );
    }

    public function test_in_limbo_scope_returns_approved_active_artists_without_published_tracks(): void
    {
        [$limboProfile] = $this->createArtistProfileWithTrack([
            'slug' => 'limbo-scope-artist',
        ], [
            'slug' => 'limbo-scope-track',
            'is_published' => false,
        ]);

        [$liveProfile] = $this->createArtistProfileWithTrack([
            'slug' => 'live-scope-artist',
        ], [
            'slug' => 'live-scope-track',
            'is_published' => true,
        ]);

        $limboIds = ArtistProfile::query()->inLimbo()->pluck('id');

        $this->assertTrue($limboIds->contains($limboProfile->id));
        $this->assertFalse($limboIds->contains($liveProfile->id));
    }

    public function test_public_artist_page_returns_404_when_artist_has_no_published_tracks(): void
    {
        [$profile, $track] = $this->createArtistProfileWithTrack([
            'slug' => 'public-limbo-artist',
        ], [
            'slug' => 'public-limbo-track',
            'is_published' => true,
        ]);

        $this->get(route('artist.page', $profile))
            ->assertOk();

        $track->update([
            'is_published' => false,
        ]);

        $this->get(route('artist.page', $profile))
            ->assertNotFound();
    }

    public function test_search_page_and_sitemap_exclude_artists_in_limbo(): void
    {
        [$limboProfile] = $this->createArtistProfileWithTrack([
            'stage_name' => 'Limbo Search Artist',
            'slug' => 'limbo-search-artist',
        ], [
            'slug' => 'limbo-search-track',
            'is_published' => false,
        ]);

        [$liveProfile] = $this->createArtistProfileWithTrack([
            'stage_name' => 'Live Search Artist',
            'slug' => 'live-search-artist',
        ], [
            'slug' => 'live-search-track',
            'is_published' => true,
        ]);

        $this->get(route('search', ['q' => 'Search Artist']))
            ->assertOk()
            ->assertSee('Live Search Artist')
            ->assertDontSee('Limbo Search Artist');

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('artist.page', $liveProfile->slug), escape: false)
            ->assertDontSee(route('artist.page', $limboProfile->slug), escape: false);
    }

    private function createArtistProfileWithTrack(array $profileAttributes = [], array $trackAttributes = []): array
    {
        $user = User::factory()->create();

        $profile = ArtistProfile::create(array_merge([
            'user_id' => $user->id,
            'stage_name' => 'Discoverability Artist ' . fake()->unique()->word(),
            'slug' => 'discoverability-artist-' . fake()->unique()->slug(),
            'is_approved' => true,
            'is_active' => true,
        ], $profileAttributes));

        $track = Track::create(array_merge([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Discoverability Track',
            'slug' => 'discoverability-track-' . fake()->unique()->slug(),
            'is_published' => true,
            'is_free' => true,
        ], $trackAttributes));

        return [$profile, $track, $user];
    }
}