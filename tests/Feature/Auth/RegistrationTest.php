<?php

namespace Tests\Feature\Auth;

use App\Notifications\Auth\BrandedVerifyEmail;
use App\Support\UploadLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
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
            ->assertSeeVolt('pages.auth.register');
    }

    public function test_new_users_can_register(): void
    {
        Notification::fake();

        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('captcha', '7');

        session(['captcha_answer' => 7]);

        $component->call('register');

        $component->assertRedirect(route('verification.notice', absolute: false));

        $this->assertAuthenticated();
        Notification::assertSentTo(auth()->user(), BrandedVerifyEmail::class);
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
            ->set('captcha', '9');

        session(['captcha_answer' => 9]);

        $component
            ->set('avatar', UploadedFile::fake()->image('avatar.jpg')->size(UploadLimits::DEFAULT_IMAGE_KB + 1))
            ->call('register')
            ->assertHasErrors(['avatar' => 'max']);
    }
}
