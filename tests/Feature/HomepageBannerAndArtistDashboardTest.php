<?php

namespace Tests\Feature;

use App\Models\ArtistProfile;
use App\Models\HomepageBannerSlide;
use App\Models\Like;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HomepageBannerAndArtistDashboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function homepage_falls_back_to_the_static_hero_when_no_banner_slides_exist(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('GrinMuzik')
            ->assertSee('Independent music. Real artists. Support the creators you love.');
    }

    #[Test]
    public function homepage_renders_admin_configured_banner_slides(): void
    {
        Storage::fake('public');

        HomepageBannerSlide::create([
            'name' => 'Launch Slide',
            'badge_text' => 'Fresh from admin',
            'heading' => 'Spotlight the next wave',
            'body' => 'Promote collections, premieres, or events without touching the template.',
            'primary_button_label' => 'Browse now',
            'primary_button_url' => route('browse'),
            'background_image' => 'homepage-banners/launch.jpg',
            'show_overlay_content' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Fresh from admin')
            ->assertSee('Spotlight the next wave')
            ->assertSee('Browse now');
    }

    #[Test]
    public function homepage_featured_artists_section_uses_the_linked_user_avatar_when_profile_avatar_is_missing(): void
    {
        Storage::fake('public');
        Cache::flush();

        $user = User::factory()->create([
            'name' => 'Avatar Fallback User',
        ]);

        $user->addMedia(UploadedFile::fake()->image('avatar.jpg'))
            ->toMediaCollection('avatar');

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Fallback Artist',
            'slug' => 'fallback-artist',
            'is_approved' => true,
            'is_active' => true,
            'followers_count' => 999,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Featured Artists')
            ->assertSee($profile->fresh()->getAvatarMediaUrl(), escape: false);
    }

    #[Test]
    public function signed_in_homepage_places_suggested_after_featured_artists(): void
    {
        Cache::flush();

        $listener = User::factory()->create();
        $artist = User::factory()->create(['name' => 'Featured Artist User']);

        $profile = ArtistProfile::create([
            'user_id' => $artist->id,
            'stage_name' => 'Featured Artist',
            'slug' => 'featured-artist',
            'is_approved' => true,
            'is_active' => true,
            'followers_count' => 999,
        ]);

        $seedTrack = Track::create([
            'user_id' => $artist->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Seed Track',
            'slug' => 'seed-track',
            'is_published' => true,
            'is_free' => true,
            'play_count' => 50,
            'downloads_count' => 10,
        ]);

        Track::create([
            'user_id' => $artist->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Suggested Track',
            'slug' => 'suggested-track',
            'is_published' => true,
            'is_free' => true,
            'play_count' => 75,
            'downloads_count' => 20,
        ]);

        Like::create([
            'user_id' => $listener->id,
            'track_id' => $seedTrack->id,
        ]);

        $this->actingAs($listener)
            ->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder([
                'Featured Artists',
                'Suggested',
            ]);
    }

    #[Test]
    public function artist_dashboard_shows_total_downloads_metric(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('artist', 'web');
        $user->assignRole('artist');

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Dashboard Artist',
            'slug' => 'dashboard-artist',
            'is_approved' => true,
            'is_active' => true,
        ]);

        Track::create([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Downloaded Track',
            'slug' => 'downloaded-track',
            'is_published' => true,
            'is_free' => true,
            'downloads_count' => 42,
        ]);

        $this->actingAs($user);

        $this->get(route('artist.dashboard'))
            ->assertOk()
            ->assertSee('Total Downloads')
            ->assertSee('42');
    }
}
