<?php

namespace Tests\Feature;

use App\Models\ArtistProfile;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArtistVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_marks_the_artist_profile_verified_and_approved(): void
    {
        $user = User::factory()->unverified()->create();
        Role::findOrCreate('artist', 'web');
        $user->assignRole('artist');

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Signal Fire',
            'slug' => 'signal-fire',
            'is_verified' => false,
            'is_approved' => false,
            'is_active' => true,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $this->actingAs($user)
            ->get($verificationUrl)
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $profile->refresh();

        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($profile->is_verified);
        $this->assertTrue($profile->is_approved);
    }

    public function test_admin_manual_verification_path_can_verify_and_approve_an_artist(): void
    {
        $user = User::factory()->unverified()->create();
        Role::findOrCreate('artist', 'web');
        $user->assignRole('artist');

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Midnight Echo',
            'slug' => 'midnight-echo',
            'is_verified' => false,
            'is_approved' => false,
            'is_active' => true,
        ]);

        $user->manuallyVerifyAndApproveArtist();

        $user->refresh();
        $profile->refresh();

        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($profile->is_verified);
        $this->assertTrue($profile->is_approved);
    }

    public function test_disabling_registration_email_verification_auto_activates_artist_profiles(): void
    {
        SiteSetting::current()->update([
            'registration_requires_email_verification' => false,
        ]);

        $user = User::factory()->unverified()->create();
        Role::findOrCreate('artist', 'web');
        $user->assignRole('artist');

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Neon Harbour',
            'slug' => 'neon-harbour',
            'is_verified' => false,
            'is_approved' => false,
            'is_active' => true,
        ]);

        $user->refresh();
        $profile->refresh();

        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->shouldAutoActivateArtistProfile());
        $this->assertTrue($profile->is_verified);
        $this->assertTrue($profile->is_approved);
    }

    public function test_changing_an_artist_email_resets_artist_verification_and_approval_until_reverified(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('artist', 'web');
        $user->assignRole('artist');

        $profile = ArtistProfile::create([
            'user_id' => $user->id,
            'stage_name' => 'Northern Lights',
            'slug' => 'northern-lights',
            'is_verified' => true,
            'is_approved' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->set('name', $user->name)
            ->set('email', 'new-artist@example.com')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user->refresh();
        $profile->refresh();

        $this->assertNull($user->email_verified_at);
        $this->assertFalse($profile->is_verified);
        $this->assertFalse($profile->is_approved);
    }
}
