<?php

namespace Tests\Feature;

use App\Models\ArtistProfile;
use App\Models\SiteSetting;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArtistProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_artist_dashboard_exposes_profile_management_links(): void
    {
        Role::findOrCreate('artist', 'web');

        $user = User::factory()->create();
        $user->assignRole('artist');

        ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Dashboard Artist',
            'slug' => 'dashboard-artist',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('artist.dashboard'))
            ->assertOk()
            ->assertSee(route('artist.edit-profile'), escape: false)
            ->assertSee(route('profile'), escape: false);
    }

    public function test_artist_can_update_their_public_profile_and_avatar(): void
    {
        Storage::fake('public');
        Role::findOrCreate('artist', 'web');

        $user = User::factory()->create();
        $user->assignRole('artist');

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Original Artist',
            'slug' => 'original-artist',
            'bio' => 'Original bio',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $component = Volt::test('pages.artist.edit-profile')
            ->set('stageName', 'Midnight Avenue')
            ->set('bio', 'Updated artist bio')
            ->set('website', 'https://example.com')
            ->set('youtube', 'https://youtube.com/@midnightavenue')
            ->set('spotify', 'https://open.spotify.com/artist/123456789')
            ->set('avatar', UploadedFile::fake()->image('artist-avatar.jpg'))
            ->call('save');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $profile->refresh();

        $this->assertSame('Midnight Avenue', $profile->stage_name);
        $this->assertSame('Updated artist bio', $profile->bio);
        $this->assertSame('https://example.com', $profile->website);
        $this->assertSame('https://youtube.com/@midnightavenue', $profile->youtube);
        $this->assertSame('https://open.spotify.com/artist/123456789', $profile->spotify);
        $this->assertNotNull($profile->getFirstMedia('avatar'));
        $this->assertStringStartsWith("artists/{$profile->id}/avatar/", $profile->getFirstMedia('avatar')->getPathRelativeToRoot());
    }

    public function test_public_artist_page_uses_the_uploaded_profile_media_and_displays_saved_social_links(): void
    {
        Storage::fake('public');
        Role::findOrCreate('artist', 'web');

        $user = User::factory()->create();
        $user->assignRole('artist');

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Social Artist',
            'slug' => 'social-artist',
            'bio' => 'A public-facing bio',
            'website' => 'https://example.com',
            'instagram' => 'socialartist',
            'twitter' => '@socialartist',
            'youtube' => 'https://youtube.com/@socialartist',
            'spotify' => 'https://open.spotify.com/artist/abc123',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $profile->addMedia(UploadedFile::fake()->image('artist-avatar.jpg'))
            ->toMediaCollection('avatar');

        Track::create([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Social Artist Track',
            'slug' => 'social-artist-track',
            'is_published' => true,
            'is_free' => true,
        ]);

        $avatarUrl = $profile->fresh()->getAvatarMediaUrl();

        $this->get(route('artist.page', $profile))
            ->assertOk()
            ->assertSee(Str::after($avatarUrl, 'http://localhost'), escape: false)
            ->assertSee('https://example.com', escape: false)
            ->assertSee('https://instagram.com/socialartist', escape: false)
            ->assertSee('https://twitter.com/socialartist', escape: false)
            ->assertSee('https://youtube.com/@socialartist', escape: false)
            ->assertSee('https://open.spotify.com/artist/abc123', escape: false);
    }

    public function test_artist_and_track_pages_fall_back_to_the_site_logo_when_media_is_missing(): void
    {
        Storage::fake('public');
        Role::findOrCreate('artist', 'web');

        SiteSetting::current()->update([
            'site_logo' => 'branding/site-logo.png',
        ]);

        $user = User::factory()->create();
        $user->assignRole('artist');

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Logo Fallback Artist',
            'slug' => 'logo-fallback-artist',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $track = Track::create([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Logo Fallback Track',
            'slug' => 'logo-fallback-track',
            'is_published' => true,
            'is_free' => true,
        ]);

        $siteLogoUrl = SiteSetting::current()->fresh()->site_logo_url;

        $this->assertSame($siteLogoUrl, $profile->fresh()->getAvatarMediaUrl());
        $this->assertSame($siteLogoUrl, $track->fresh()->getCoverUrl());

        $this->get(route('artist.page', $profile))
            ->assertOk()
            ->assertSee($siteLogoUrl, escape: false);

        $this->get(route('track.show', $track))
            ->assertOk()
            ->assertSee($siteLogoUrl, escape: false);
    }
}
