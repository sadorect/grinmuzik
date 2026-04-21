<?php

namespace Tests\Feature;

use App\Models\ArtistProfile;
use App\Models\InternalMessage;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InternalMessageAudienceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function filtered_broadcasts_only_target_matching_users(): void
    {
        $admin = User::factory()->create();

        $matchingArtist = User::factory()->create([
            'email_verified_at' => now(),
            'last_seen_at' => now()->subDay(),
        ]);
        Role::findOrCreate('artist', 'web');
        $matchingArtist->assignRole('artist');

        $profile = ArtistProfile::create([
            'user_id' => $matchingArtist->id,
            'stage_name' => 'Target Artist',
            'slug' => 'target-artist',
            'is_verified' => true,
            'is_approved' => true,
            'is_active' => true,
            'total_plays' => 1200,
            'followers_count' => 48,
            'total_donations' => 99.50,
        ]);

        Track::create([
            'user_id' => $matchingArtist->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Signal Track',
            'slug' => 'signal-track',
            'is_published' => true,
            'is_free' => true,
            'donation_amount' => 1,
        ]);

        $inactiveArtist = User::factory()->create([
            'email_verified_at' => now(),
            'last_seen_at' => now()->subDays(120),
        ]);
        $inactiveArtist->assignRole('artist');
        ArtistProfile::create([
            'user_id' => $inactiveArtist->id,
            'stage_name' => 'Inactive Artist',
            'slug' => 'inactive-artist',
            'is_verified' => true,
            'is_approved' => true,
            'is_active' => true,
        ]);

        $listener = User::factory()->create([
            'email_verified_at' => now(),
            'last_seen_at' => now(),
        ]);
        Role::findOrCreate('listener', 'web');
        $listener->assignRole('listener');

        $message = InternalMessage::create([
            'created_by' => $admin->id,
            'subject' => 'Artist check-in',
            'body' => 'Hello {first_name}',
            'audience' => 'artists',
            'audience_filters' => [
                'email_status' => 'verified',
                'activity_window' => 'active_30d',
                'engagement_segment' => 'has_uploads',
            ],
            'send_email' => false,
            'sent_at' => now(),
        ]);

        $this->assertTrue($message->targetsUser($matchingArtist));
        $this->assertFalse($message->targetsUser($inactiveArtist));
        $this->assertFalse($message->targetsUser($listener));
        $this->assertSame([$matchingArtist->id], $message->recipientUsers()->pluck('id')->all());

        $this->actingAs($matchingArtist)
            ->get(route('inbox'))
            ->assertOk()
            ->assertSee('Artist check-in');

        $this->actingAs($listener)
            ->get(route('inbox'))
            ->assertOk()
            ->assertDontSee('Artist check-in');
    }

    #[Test]
    public function internal_messages_support_extended_personalization_tokens(): void
    {
        $user = User::factory()->create([
            'name' => 'Ayo Stone',
            'email' => 'ayo@example.com',
            'email_verified_at' => now(),
            'last_seen_at' => now(),
        ]);

        Role::findOrCreate('artist', 'web');
        $user->assignRole('artist');

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Ayo Wave',
            'slug' => 'ayo-wave',
            'is_verified' => true,
            'is_approved' => true,
            'is_active' => true,
            'total_plays' => 3450,
            'followers_count' => 88,
            'total_donations' => 125.25,
        ]);

        Track::create([
            'user_id' => $user->id,
            'artist_profile_id' => $profile->id,
            'title' => 'Neon Tide',
            'slug' => 'neon-tide',
            'is_published' => true,
            'is_free' => true,
            'donation_amount' => 1,
            'play_count' => 3450,
        ]);

        $message = new InternalMessage([
            'subject' => 'Hi {first_name}',
            'body' => '{artist_stage_name}|{role_label}|{artist_tracks_count}|{artist_total_plays}|{artist_profile_url}|{dashboard_url}',
            'audience' => 'artists',
        ]);

        $interpolated = $message->interpolate($message->body, $user);

        $this->assertStringContainsString('Ayo Wave', $interpolated);
        $this->assertStringContainsString('Artist', $interpolated);
        $this->assertStringContainsString('|1|', $interpolated);
        $this->assertStringContainsString('3450', $interpolated);
        $this->assertStringContainsString(route('artist.page', $profile), $interpolated);
        $this->assertStringContainsString(route('artist.dashboard'), $interpolated);
    }
}
