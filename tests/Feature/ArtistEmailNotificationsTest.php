<?php

namespace Tests\Feature;

use App\Models\ArtistProfile;
use App\Models\Track;
use App\Models\User;
use App\Notifications\Artist\ArtistActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArtistEmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function createArtist(): array
    {
        Role::findOrCreate('artist', 'web');

        $user = User::factory()->create();
        $user->assignRole('artist');

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Notification Artist',
            'slug' => 'notification-artist',
            'is_approved' => true,
            'is_active' => true,
        ]);

        return [$user, $profile];
    }

    #[Test]
    public function artist_password_updates_send_an_account_activity_email(): void
    {
        [$artist] = $this->createArtist();

        Notification::fake();

        $this->actingAs($artist);

        Volt::test('profile.update-password-form')
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword')
            ->assertHasNoErrors();

        Notification::assertSentTo($artist, ArtistActivityNotification::class, function (ArtistActivityNotification $notification) {
            return $notification->subject === 'Your artist password was changed';
        });
    }

    #[Test]
    public function artist_play_milestones_are_only_emailed_once_per_threshold(): void
    {
        [$artist, $profile] = $this->createArtist();

        $track = Track::create([
            'user_id' => $artist->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Milestone Track',
            'slug' => 'milestone-track',
            'is_published' => true,
            'is_free' => true,
            'play_count' => 99,
        ]);

        Notification::fake();

        $track->incrementPlayCount();
        $track->incrementPlayCount();

        Notification::assertSentTo($artist, ArtistActivityNotification::class, function (ArtistActivityNotification $notification) {
            return str_contains($notification->subject, '100 plays');
        });

        Notification::assertCount(1);
    }
}
