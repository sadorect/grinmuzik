<?php

namespace Tests\Feature;

use App\Models\ArtistProfile;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistFeaturedRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_recommendation_requires_discoverability_minimum_catalog_and_traction(): void
    {
        $eligibleProfile = $this->createArtistWithPublishedTracks(
            profileAttributes: [
                'total_plays' => 1200,
                'followers_count' => 40,
            ],
            trackCount: 3,
            perTrackAttributes: [
                'downloads_count' => 30,
            ],
        );

        $ineligibleProfile = $this->createArtistWithPublishedTracks(
            profileAttributes: [
                'total_plays' => 400,
                'followers_count' => 10,
            ],
            trackCount: 2,
            perTrackAttributes: [
                'downloads_count' => 5,
            ],
        );

        $eligibleProfile = ArtistProfile::query()->withFeaturedRecommendationMetrics()->findOrFail($eligibleProfile->id);
        $ineligibleProfile = ArtistProfile::query()->withFeaturedRecommendationMetrics()->findOrFail($ineligibleProfile->id);

        $this->assertTrue($eligibleProfile->is_featured_recommendation_eligible);
        $this->assertGreaterThan(0, $eligibleProfile->featured_recommendation_score);
        $this->assertFalse($ineligibleProfile->is_featured_recommendation_eligible);
        $this->assertSame(0, $ineligibleProfile->featured_recommendation_score);
    }

    public function test_feature_recommendation_score_prefers_stronger_artist_signals(): void
    {
        $baselineProfile = $this->createArtistWithPublishedTracks(
            profileAttributes: [
                'total_plays' => 900,
                'followers_count' => 30,
                'total_donations' => 0,
            ],
            trackCount: 3,
            perTrackAttributes: [
                'downloads_count' => 10,
            ],
        );

        $strongProfile = $this->createArtistWithPublishedTracks(
            profileAttributes: [
                'total_plays' => 5400,
                'followers_count' => 220,
                'total_donations' => 25,
                'is_verified' => true,
            ],
            trackCount: 5,
            perTrackAttributes: [
                'downloads_count' => 65,
            ],
        );

        $baselineProfile = ArtistProfile::query()->withFeaturedRecommendationMetrics()->findOrFail($baselineProfile->id);
        $strongProfile = ArtistProfile::query()->withFeaturedRecommendationMetrics()->findOrFail($strongProfile->id);

        $this->assertGreaterThan($baselineProfile->featured_recommendation_score, $strongProfile->featured_recommendation_score);
        $this->assertGreaterThan(0, $baselineProfile->featured_recommendation_score);
        $this->assertTrue($strongProfile->is_featured_recommended);
        $this->assertFalse($baselineProfile->is_featured_recommended);
    }

    public function test_featured_listing_ranking_prioritizes_admin_featured_then_recommended_artists(): void
    {
        $featuredProfile = $this->createArtistWithPublishedTracks(
            profileAttributes: [
                'stage_name' => 'Manual Featured',
                'slug' => 'manual-featured',
                'total_plays' => 700,
                'followers_count' => 28,
                'is_featured' => true,
            ],
            trackCount: 3,
            perTrackAttributes: [
                'downloads_count' => 8,
            ],
        );

        $recommendedProfile = $this->createArtistWithPublishedTracks(
            profileAttributes: [
                'stage_name' => 'Recommended Artist',
                'slug' => 'recommended-artist',
                'total_plays' => 6200,
                'followers_count' => 260,
                'total_donations' => 30,
                'is_verified' => true,
            ],
            trackCount: 5,
            perTrackAttributes: [
                'downloads_count' => 70,
            ],
        );

        $eligibleProfile = $this->createArtistWithPublishedTracks(
            profileAttributes: [
                'stage_name' => 'Eligible Artist',
                'slug' => 'eligible-artist',
                'total_plays' => 1000,
                'followers_count' => 35,
            ],
            trackCount: 3,
            perTrackAttributes: [
                'downloads_count' => 12,
            ],
        );

        $rankedProfiles = ArtistProfile::query()
            ->withFeaturedRecommendationMetrics()
            ->whereKey([$eligibleProfile->id, $recommendedProfile->id, $featuredProfile->id])
            ->get();

        $orderedNames = ArtistProfile::rankForFeaturedListing($rankedProfiles)
            ->pluck('stage_name')
            ->all();

        $this->assertSame([
            'Manual Featured',
            'Recommended Artist',
            'Eligible Artist',
        ], $orderedNames);
    }

    private function createArtistWithPublishedTracks(array $profileAttributes = [], int $trackCount = 3, array $perTrackAttributes = []): ArtistProfile
    {
        $user = User::factory()->create();

        $profile = ArtistProfile::create(array_merge([
            'user_id' => $user->id,
            'stage_name' => 'Feature Artist ' . fake()->unique()->word(),
            'slug' => 'feature-artist-' . fake()->unique()->slug(),
            'is_approved' => true,
            'is_active' => true,
            'is_verified' => false,
            'total_plays' => 0,
            'followers_count' => 0,
            'total_donations' => 0,
        ], $profileAttributes));

        $profile->forceFill([
            'total_plays' => $profileAttributes['total_plays'] ?? 0,
            'followers_count' => $profileAttributes['followers_count'] ?? 0,
            'total_donations' => $profileAttributes['total_donations'] ?? 0,
        ])->save();

        foreach (range(1, $trackCount) as $index) {
            Track::create(array_merge([
                'user_id' => $user->id,
                'artist_profile_id' => $profile->id,
                'title' => $profile->slug . '-track-' . $index,
                'slug' => $profile->slug . '-track-' . $index,
                'is_published' => true,
                'is_free' => true,
                'downloads_count' => 0,
                'created_at' => now()->subDays($index * 5),
                'updated_at' => now()->subDays($index * 5),
            ], $perTrackAttributes));
        }

        return $profile;
    }
}