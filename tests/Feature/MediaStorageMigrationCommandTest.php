<?php

namespace Tests\Feature;

use App\Models\ArtistProfile;
use App\Models\HomepageBannerSlide;
use App\Models\SiteSetting;
use App\Models\Track;
use App\Models\User;
use App\Support\MediaLibrary\OrganizedPathGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;
use Tests\TestCase;

class MediaStorageMigrationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_migration_command_moves_legacy_assets_into_the_organized_layout(): void
    {
        Storage::fake('public');
        Storage::fake('r2_public');
        Storage::fake('r2_private');

        config()->set('media-storage.public_disk', 'public');
        config()->set('media-storage.private_disk', 'local');
        config()->set('media-library.disk_name', 'public');
        config()->set('media-library.path_generator', DefaultPathGenerator::class);

        $user = User::factory()->create();
        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Migration Artist',
            'slug' => 'migration-artist',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $profile->addMedia(UploadedFile::fake()->image('avatar.jpg'))
            ->toMediaCollection('avatar');

        $legacyMedia = $profile->fresh()->getFirstMedia('avatar');
        $legacyMediaPath = $legacyMedia->getPathRelativeToRoot();

        Storage::disk('public')->put('audio/tracks/demo.mp3', 'demo-audio');
        Storage::disk('public')->put('homepage-banners/launch.jpg', 'demo-slide');
        Storage::disk('public')->put('site-branding/logo.png', 'demo-logo');
        Storage::disk('public')->put('site-branding/favicon.png', 'demo-favicon');

        $track = Track::create([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Legacy Audio',
            'slug' => 'legacy-audio',
            'is_published' => true,
            'is_free' => true,
            'audio_file_path' => 'audio/tracks/demo.mp3',
        ]);

        $slide = HomepageBannerSlide::create([
            'name' => 'Launch Slide',
            'heading' => 'Migration Slide',
            'background_image' => 'homepage-banners/launch.jpg',
            'is_active' => true,
        ]);

        $settings = SiteSetting::current();
        $settings->update([
            'site_logo' => 'site-branding/logo.png',
            'favicon' => 'site-branding/favicon.png',
        ]);

        config()->set('media-storage.public_disk', 'r2_public');
        config()->set('media-storage.private_disk', 'r2_private');
        config()->set('media-library.disk_name', 'r2_public');
        config()->set('media-library.path_generator', OrganizedPathGenerator::class);

        $this->artisan('media:migrate-organized-storage')
            ->assertExitCode(0);

        $migratedMedia = $legacyMedia->fresh();

        $this->assertSame('r2_public', $migratedMedia->disk);
        Storage::disk('r2_public')->assertExists("artists/{$profile->id}/avatar/{$migratedMedia->file_name}");
        Storage::disk('public')->assertExists($legacyMediaPath);

        $this->assertSame("tracks/{$track->id}/audio/original.mp3", $track->fresh()->audio_file_path);
        Storage::disk('r2_private')->assertExists("tracks/{$track->id}/audio/original.mp3");

        $this->assertSame("site/homepage-slides/{$slide->id}/background/original.jpg", $slide->fresh()->background_image);
        Storage::disk('r2_public')->assertExists("site/homepage-slides/{$slide->id}/background/original.jpg");

        $this->assertSame('site/branding/logo/current.png', $settings->fresh()->site_logo);
        $this->assertSame('site/branding/favicon/current.png', $settings->fresh()->favicon);
        Storage::disk('r2_public')->assertExists('site/branding/logo/current.png');
        Storage::disk('r2_public')->assertExists('site/branding/favicon/current.png');
    }
}
