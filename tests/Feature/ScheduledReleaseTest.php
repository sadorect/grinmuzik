<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\ArtistProfile;
use App\Models\Track;
use App\Models\User;
use App\Notifications\Artist\ArtistActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduledReleaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-09 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function artist_can_schedule_a_track_at_upload_and_it_stays_hidden(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        [$user] = $this->createArtist();
        $this->actingAs($user);

        Volt::test('pages.artist.upload-track')
            ->set('title', 'Later Song')
            ->set('audioFile', UploadedFile::fake()->create('later.mp3', 512, 'audio/mpeg'))
            ->set('release_mode', 'scheduled')
            ->set('release_at', '2026-10-10T18:30:00.000Z')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('artist.tracks'));

        $track = Track::query()->firstOrFail();

        $this->assertFalse($track->is_published);
        $this->assertTrue($track->is_scheduled);
        $this->assertSame('2026-10-10 18:30:00', $track->scheduled_release_at->utc()->toDateTimeString());
        $this->assertSame(0, Track::published()->count());
    }

    #[Test]
    public function scheduling_requires_a_future_time(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        [$user] = $this->createArtist();
        $this->actingAs($user);

        $component = Volt::test('pages.artist.upload-track')
            ->set('title', 'Past Song')
            ->set('audioFile', UploadedFile::fake()->create('past.mp3', 512, 'audio/mpeg'))
            ->set('release_mode', 'scheduled');

        $component->set('release_at', null)->call('save')->assertHasErrors(['release_at' => 'required']);
        $component->set('release_at', '2026-10-09T11:00:00Z')->call('save')->assertHasErrors(['release_at' => 'after']);

        $this->assertSame(0, Track::count());
    }

    #[Test]
    public function a_stale_time_is_ignored_when_publishing_now(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        [$user] = $this->createArtist();
        $this->actingAs($user);

        Volt::test('pages.artist.upload-track')
            ->set('title', 'Now Song')
            ->set('audioFile', UploadedFile::fake()->create('now.mp3', 512, 'audio/mpeg'))
            ->set('release_mode', 'now')
            ->set('release_at', '2020-01-01T00:00:00Z')
            ->call('save')
            ->assertHasNoErrors();

        $track = Track::query()->firstOrFail();
        $this->assertTrue($track->is_published);
        $this->assertNull($track->scheduled_release_at);
    }

    #[Test]
    public function the_command_releases_only_due_tracks_dated_to_their_scheduled_time(): void
    {
        Notification::fake();
        [$user, $profile] = $this->createArtist();
        $due = $this->makeTrack($profile, ['scheduled_release_at' => '2026-10-09 11:59:00']);
        $future = $this->makeTrack($profile, ['scheduled_release_at' => '2026-10-09 12:30:00']);

        $this->artisan('releases:publish-scheduled')->assertSuccessful();

        $due->refresh();
        $this->assertTrue($due->is_published);
        $this->assertNull($due->scheduled_release_at);
        $this->assertSame('2026-10-09 11:59:00', $due->published_at->toDateTimeString());
        $this->assertFalse($future->fresh()->is_published);

        Notification::assertSentToTimes($user, ArtistActivityNotification::class, 1);
        Notification::assertSentTo($user, ArtistActivityNotification::class, fn ($n) => $n->subject === 'Now live: ' . $due->title);

        // A second (or duplicate) scheduler run finds nothing to do.
        $this->artisan('releases:publish-scheduled')->assertSuccessful();
        Notification::assertSentToTimes($user, ArtistActivityNotification::class, 1);
    }

    #[Test]
    public function two_runners_cannot_both_release_the_same_track(): void
    {
        [, $profile] = $this->createArtist();
        $track = $this->makeTrack($profile, ['scheduled_release_at' => '2026-10-09 11:00:00']);

        $first = Track::find($track->id);
        $second = Track::find($track->id);

        $this->assertTrue($first->releaseIfDue());
        $this->assertFalse($second->releaseIfDue());
    }

    #[Test]
    public function publishing_by_hand_clears_the_schedule_so_a_later_unpublish_sticks(): void
    {
        [, $profile] = $this->createArtist();
        $track = $this->makeTrack($profile, ['scheduled_release_at' => '2026-10-10 09:00:00']);

        $track->update(['is_published' => true]);
        $this->assertNull($track->fresh()->scheduled_release_at);

        $track->update(['is_published' => false]);
        Carbon::setTestNow('2026-10-11 00:00:00');
        $this->artisan('releases:publish-scheduled')->assertSuccessful();

        $this->assertFalse($track->fresh()->is_published);
    }

    #[Test]
    public function hiding_reported_content_cancels_its_schedule(): void
    {
        [, $profile] = $this->createArtist();
        $track = $this->makeTrack($profile, ['scheduled_release_at' => '2026-10-10 09:00:00']);

        $track->hideFromPublic();

        $this->assertNull($track->fresh()->scheduled_release_at);
    }

    #[Test]
    public function an_album_release_takes_its_scheduled_tracks_with_it_in_one_email(): void
    {
        Notification::fake();
        [$user, $profile] = $this->createArtist();
        $album = $this->makeAlbum($profile, ['scheduled_release_at' => '2026-10-09 11:00:00']);
        $tied = $this->makeTrack($profile, ['album_id' => $album->id, 'scheduled_release_at' => '2026-10-09 11:00:00']);
        $draft = $this->makeTrack($profile, ['album_id' => $album->id]);

        $this->artisan('releases:publish-scheduled')->assertSuccessful();

        $this->assertTrue($album->fresh()->is_published);
        $this->assertTrue($tied->fresh()->is_published);
        $this->assertSame('2026-10-09 11:00:00', $tied->fresh()->published_at->toDateTimeString());
        $this->assertFalse($draft->fresh()->is_published);

        Notification::assertSentToTimes($user, ArtistActivityNotification::class, 1);
        Notification::assertSentTo($user, ArtistActivityNotification::class, fn ($n) => $n->subject === 'Now live: ' . $album->title
            && str_contains(implode(' ', $n->messageLines), '1 track scheduled with it'));
    }

    #[Test]
    public function rescheduling_or_drafting_an_album_carries_its_tied_tracks(): void
    {
        [, $profile] = $this->createArtist();
        $album = $this->makeAlbum($profile, ['scheduled_release_at' => '2026-10-20 10:00:00']);
        $tied = $this->makeTrack($profile, ['album_id' => $album->id, 'scheduled_release_at' => '2026-10-20 10:00:00']);
        $ownTime = $this->makeTrack($profile, ['album_id' => $album->id, 'scheduled_release_at' => '2026-10-25 10:00:00']);

        $album->update(['scheduled_release_at' => '2026-10-22 10:00:00']);
        $this->assertSame('2026-10-22 10:00:00', $tied->fresh()->scheduled_release_at->toDateTimeString());
        $this->assertSame('2026-10-25 10:00:00', $ownTime->fresh()->scheduled_release_at->toDateTimeString());

        $album->update(['scheduled_release_at' => null]);
        $this->assertFalse($tied->fresh()->is_scheduled);
        $this->assertTrue($ownTime->fresh()->is_scheduled);
    }

    #[Test]
    public function publishing_a_scheduled_album_early_releases_its_tied_tracks_now(): void
    {
        [, $profile] = $this->createArtist();
        $album = $this->makeAlbum($profile, ['scheduled_release_at' => '2026-10-20 10:00:00']);
        $tied = $this->makeTrack($profile, ['album_id' => $album->id, 'scheduled_release_at' => '2026-10-20 10:00:00']);

        $album->update(['is_published' => true]);

        $tied->refresh();
        $this->assertTrue($tied->is_published);
        $this->assertSame('2026-10-09 12:00:00', $tied->published_at->toDateTimeString());
    }

    #[Test]
    public function creating_a_scheduled_album_holds_its_unreleased_tracks_until_then(): void
    {
        Storage::fake('public');
        [$user, $profile] = $this->createArtist();
        $draft = $this->makeTrack($profile);
        $live = $this->makeTrack($profile, ['is_published' => true]);
        $this->actingAs($user);

        Volt::test('pages.artist.create-album')
            ->set('title', 'Next Era')
            ->set('release_mode', 'scheduled')
            ->set('release_at', '2026-11-01T20:00:00Z')
            ->set('selectedTrackIds', [$draft->id, $live->id])
            ->call('save')
            ->assertHasNoErrors();

        $album = Album::query()->firstOrFail();
        $this->assertTrue($album->is_scheduled);
        $this->assertSame('2026-11-01', $album->release_date->toDateString());
        $this->assertSame('Scheduled', $album->release_status_label);
        $this->assertSame('2026-11-01 20:00:00', $draft->fresh()->scheduled_release_at->toDateTimeString());
        $this->assertTrue($live->fresh()->is_published);
    }

    #[Test]
    public function editing_a_live_track_can_schedule_it_and_the_form_shows_the_schedule(): void
    {
        [$user, $profile] = $this->createArtist();
        $track = $this->makeTrack($profile, ['is_published' => true]);
        $this->actingAs($user);

        Volt::test('pages.artist.edit-track', ['track' => $track])
            ->assertSet('release_mode', 'now')
            ->set('release_mode', 'scheduled')
            ->set('release_at', '2026-10-12T08:00:00Z')
            ->call('save')
            ->assertHasNoErrors();

        $track->refresh();
        $this->assertFalse($track->is_published);
        $this->assertTrue($track->is_scheduled);

        Volt::test('pages.artist.edit-track', ['track' => $track])
            ->assertSet('release_mode', 'scheduled')
            ->assertSet('release_at', '2026-10-12T08:00:00Z');
    }

    #[Test]
    public function the_tracks_page_lists_scheduled_tracks_separately_from_drafts(): void
    {
        [$user, $profile] = $this->createArtist();
        $this->makeTrack($profile, ['title' => 'Coming Soon', 'scheduled_release_at' => '2026-10-12 08:00:00']);
        $this->makeTrack($profile, ['title' => 'Plain Draft']);
        $this->actingAs($user);

        Volt::test('pages.artist.tracks')
            ->set('filter', 'scheduled')
            ->assertSee('Coming Soon')
            ->assertSee('2026-10-12T08:00:00Z')
            ->assertDontSee('Plain Draft')
            ->set('filter', 'drafts')
            ->assertSee('Plain Draft')
            ->assertDontSee('Coming Soon');
    }

    #[Test]
    public function an_artist_waiting_on_a_scheduled_release_is_not_treated_as_dormant(): void
    {
        [, $profile] = $this->createArtist();
        $profile->forceFill(['created_at' => now()->subDays(30)])->save();
        $this->makeTrack($profile, ['scheduled_release_at' => '2026-12-01 10:00:00']);

        $this->assertSame(0, ArtistProfile::pendingDormancyWarning()->count());
    }

    protected function createArtist(): array
    {
        $user = User::factory()->create();
        Role::findOrCreate('artist', 'web');
        $user->assignRole('artist');

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Scheduler ' . $user->id,
            'slug' => 'scheduler-' . $user->id,
            'is_approved' => true,
            'is_active' => true,
        ]);

        return [$user, $profile];
    }

    protected function makeTrack(ArtistProfile $profile, array $attributes = []): Track
    {
        $title = $attributes['title'] ?? 'Track ' . fake()->unique()->numberBetween(1, 99999);

        return Track::create(array_merge([
            'user_id' => $profile->user_id,
            'artist_profile_id' => $profile->id,
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title) . '-' . fake()->unique()->numberBetween(1, 99999),
            'is_published' => false,
            'is_free' => true,
        ], $attributes));
    }

    protected function makeAlbum(ArtistProfile $profile, array $attributes = []): Album
    {
        return Album::create(array_merge([
            'user_id' => $profile->user_id,
            'artist_profile_id' => $profile->id,
            'title' => 'Album ' . fake()->unique()->numberBetween(1, 99999),
            'type' => 'album',
            'is_published' => false,
        ], $attributes));
    }
}
