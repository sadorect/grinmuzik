<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;
use Tests\Concerns\SolvesCaptcha;
use Tests\TestCase;

class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;
    use SolvesCaptcha;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login|ip|127.0.0.1');
    }

    private function attemptLogin(string $email, string $password = 'wrong-password'): \Livewire\Features\SupportTesting\Testable
    {
        $component = Volt::test('pages.auth.login')
            ->set('form.email', $email)
            ->set('form.password', $password);

        return $component->set('captcha', $this->captchaAnswerFor('login'))->call('login');
    }

    public function test_repeated_failures_against_one_account_are_locked_out(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->attemptLogin($user->email);
        }

        $this->attemptLogin($user->email)
            ->assertHasErrors('form.email');

        $this->assertTrue(RateLimiter::tooManyAttempts('login|email|'.$user->email, 5));
    }

    public function test_password_spraying_across_many_accounts_is_locked_out(): void
    {
        // The regression this exists for: the old limiter keyed on email|ip, so
        // every fresh email was a fresh key and one password could be tried
        // against unlimited accounts from a single host.
        config()->set('security.rate_limits.login.per_ip', 10);

        User::factory()->count(12)->create();

        $emails = User::query()->pluck('email');

        foreach ($emails as $email) {
            $this->attemptLogin($email, 'Summer2026!');
        }

        $this->assertTrue(
            RateLimiter::tooManyAttempts('login|ip|127.0.0.1', 10),
            'The per-IP login limiter never tripped, so spraying is still unlimited.',
        );
    }

    public function test_a_successful_login_clears_both_counters(): void
    {
        $user = User::factory()->create();

        $this->attemptLogin($user->email);
        $this->attemptLogin($user->email);

        $this->attemptLogin($user->email, 'password')->assertHasNoErrors();

        $this->assertSame(0, RateLimiter::attempts('login|ip|127.0.0.1'));
        $this->assertSame(0, RateLimiter::attempts('login|email|'.$user->email));
    }

    public function test_reset_link_requests_are_limited_per_ip(): void
    {
        config()->set('security.rate_limits.password_reset_request.per_ip', 3);

        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            Volt::test('pages.auth.forgot-password')
                ->set('email', $user->email)
                ->set('captcha', $this->captchaAnswerFor('password-reset-request'))
                ->call('sendPasswordResetLink');
        }

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->set('captcha', $this->captchaAnswerFor('password-reset-request'))
            ->call('sendPasswordResetLink')
            ->assertHasErrors('email');
    }

    public function test_registrations_are_limited_per_ip(): void
    {
        config()->set('security.rate_limits.register.per_ip', 2);

        for ($i = 0; $i < 2; $i++) {
            Volt::test('pages.auth.register')
                ->set('role', 'listener')
                ->set('name', "User {$i}")
                ->set('email', "user{$i}@example.com")
                ->set('password', 'password')
                ->set('password_confirmation', 'password')
                ->set('captcha', $this->captchaAnswerFor('register'))
                ->set('hasAcceptedPolicies', true)
                ->call('register');
        }

        Volt::test('pages.auth.register')
            ->set('role', 'listener')
            ->set('name', 'One Too Many')
            ->set('email', 'toomany@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('captcha', $this->captchaAnswerFor('register'))
            ->set('hasAcceptedPolicies', true)
            ->call('register')
            ->assertHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'toomany@example.com']);
    }
}
