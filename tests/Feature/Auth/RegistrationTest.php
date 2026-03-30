<?php

namespace Tests\Feature\Auth;

use App\Models\SiteSetting;
use App\Support\UploadLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.register')
            ->assertSee('Choose the tab that matches your reason for joining')
            ->assertSee('Release my music')
            ->assertSee('Listen to music');
    }

    public function test_registration_screen_hides_role_guide_when_disabled_in_site_settings(): void
    {
        SiteSetting::current()->update([
            'show_registration_role_guide' => false,
        ]);

        $this->get('/register')
            ->assertOk()
            ->assertDontSee('Choose the tab that matches your reason for joining');
    }

    public function test_registration_defaults_to_artist_role(): void
    {
        Volt::test('pages.auth.register')
            ->assertSet('role', 'artist');
    }

    public function test_new_users_can_register(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('role', 'listener')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('captcha', '7')
            ->set('hasAcceptedPolicies', true);

        session(['captcha_answer' => 7]);

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertNotNull(auth()->user()->email_verified_at);
    }

    public function test_artist_avatar_must_be_two_megabytes_or_smaller(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('role', 'artist')
            ->set('name', 'Artist User')
            ->set('email', 'artist@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('stage_name', 'Artist User')
            ->set('captcha', '9')
            ->set('hasAcceptedPolicies', true);

        session(['captcha_answer' => 9]);

        $component
            ->set('avatar', UploadedFile::fake()->image('avatar.jpg')->size(UploadLimits::DEFAULT_IMAGE_KB + 1))
            ->call('register')
            ->assertHasErrors(['avatar' => 'max']);
    }

    public function test_registration_requires_policy_acceptance(): void
    {
        session(['captcha_answer' => 7]);

        Volt::test('pages.auth.register')
            ->set('role', 'listener')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('captcha', '7')
            ->set('hasAcceptedPolicies', false)
            ->call('register')
            ->assertHasErrors(['hasAcceptedPolicies' => 'accepted']);
    }
}
