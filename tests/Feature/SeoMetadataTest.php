<?php

namespace Tests\Feature;

use App\Models\ArtistProfile;
use App\Models\HomepageBannerSlide;
use App\Models\Playlist;
use App\Models\SiteSetting;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_uses_the_site_logo_for_share_cards(): void
    {
        Storage::fake('public');

        SiteSetting::current()->update([
            'site_logo' => 'branding/site-logo.png',
        ]);

        HomepageBannerSlide::create([
            'name' => 'Launch Slide',
            'heading' => 'Spotlight the next wave',
            'background_image' => 'homepage-banners/launch.jpg',
            'show_overlay_content' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $siteLogoUrl = $this->absoluteUrl(SiteSetting::current()->fresh()->site_logo_url);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="' . e($siteLogoUrl) . '">', false)
            ->assertSee('<meta name="twitter:image" content="' . e($siteLogoUrl) . '">', false);
    }

    public function test_artist_page_uses_the_artist_avatar_for_share_cards(): void
    {
        Storage::fake('public');

        SiteSetting::current()->update([
            'site_logo' => 'branding/site-logo.png',
        ]);

        $user = User::factory()->create(['name' => 'Artist Owner']);

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Avatar Artist',
            'slug' => 'avatar-artist',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $profile->addMedia(UploadedFile::fake()->image('artist-avatar.jpg'))
            ->toMediaCollection('avatar');

        $avatarUrl = $this->absoluteUrl($profile->fresh()->getShareImageUrl());

        $this->get(route('artist.page', $profile))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="' . e($avatarUrl) . '">', false)
            ->assertSee('<meta name="twitter:image" content="' . e($avatarUrl) . '">', false);
    }

    public function test_artist_page_without_avatar_falls_back_to_the_site_logo_for_share_cards(): void
    {
        Storage::fake('public');

        SiteSetting::current()->update([
            'site_logo' => 'branding/site-logo.png',
        ]);

        $user = User::factory()->create(['name' => 'Banner Only Artist']);

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Banner Only Artist',
            'slug' => 'banner-only-artist',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $profile->addMedia(UploadedFile::fake()->image('artist-banner.jpg'))
            ->toMediaCollection('banner');

        $siteLogoUrl = $this->absoluteUrl(SiteSetting::current()->fresh()->site_logo_url);

        $this->get(route('artist.page', $profile))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="' . e($siteLogoUrl) . '">', false)
            ->assertSee('<meta name="twitter:image" content="' . e($siteLogoUrl) . '">', false);
    }

    public function test_track_and_playlist_pages_use_their_own_cover_images_for_share_cards(): void
    {
        Storage::fake('public');

        SiteSetting::current()->update([
            'site_logo' => 'branding/site-logo.png',
        ]);

        $user = User::factory()->create(['name' => 'Cover Artist']);

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Cover Artist',
            'slug' => 'cover-artist',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $track = Track::create([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Cover Track',
            'slug' => 'cover-track',
            'is_published' => true,
            'is_free' => true,
        ]);

        $track->addMedia(UploadedFile::fake()->image('track-cover.jpg'))
            ->toMediaCollection('cover');

        $playlist = Playlist::create([
            'user_id' => $user->id,
            'name' => 'Cover Playlist',
            'description' => 'A cover-led playlist',
            'is_public' => true,
            'slug' => 'cover-playlist',
        ]);

        $playlist->addMedia(UploadedFile::fake()->image('playlist-cover.jpg'))
            ->toMediaCollection('cover');

        $trackCoverUrl = $this->absoluteUrl($track->fresh()->getShareImageUrl());
        $playlistCoverUrl = $this->absoluteUrl($playlist->fresh()->getShareImageUrl());

        $this->get(route('track.show', $track))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="' . e($trackCoverUrl) . '">', false)
            ->assertSee('<meta name="twitter:image" content="' . e($trackCoverUrl) . '">', false);

        $this->get(route('playlist.show', $playlist))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="' . e($playlistCoverUrl) . '">', false)
            ->assertSee('<meta name="twitter:image" content="' . e($playlistCoverUrl) . '">', false);
    }

    public function test_png_uploads_use_jpg_share_images_for_artist_and_track_cards(): void
    {
        Storage::fake('public');

        SiteSetting::current()->update([
            'site_logo' => 'branding/site-logo.png',
        ]);

        $user = User::factory()->create(['name' => 'PNG Artist']);

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'PNG Artist',
            'slug' => 'png-artist',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $profile->addMedia(UploadedFile::fake()->image('artist-avatar.png'))
            ->toMediaCollection('avatar');

        $track = Track::create([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'PNG Track',
            'slug' => 'png-track',
            'is_published' => true,
            'is_free' => true,
        ]);

        $track->addMedia(UploadedFile::fake()->image('track-cover.png'))
            ->toMediaCollection('cover');

        $artistShareImage = $this->absoluteUrl($profile->fresh()->getShareImageUrl());
        $trackShareImage = $this->absoluteUrl($track->fresh()->getShareImageUrl());

        $this->assertStringEndsWith('.jpg', strtok($artistShareImage ?? '', '?') ?: '');
        $this->assertStringEndsWith('.jpg', strtok($trackShareImage ?? '', '?') ?: '');

        $this->get(route('artist.page', $profile))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="' . e($artistShareImage) . '">', false)
            ->assertSee('<meta name="twitter:image" content="' . e($artistShareImage) . '">', false);

        $this->get(route('track.show', $track))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="' . e($trackShareImage) . '">', false)
            ->assertSee('<meta name="twitter:image" content="' . e($trackShareImage) . '">', false);
    }

    protected function absoluteUrl(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        if (Str::startsWith($value, '//')) {
            return 'http:' . $value;
        }

        return url($value);
    }
}
