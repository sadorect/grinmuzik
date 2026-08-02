<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\CaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\Concerns\SolvesCaptcha;
use Tests\TestCase;

class CaptchaTest extends TestCase
{
    use RefreshDatabase;
    use SolvesCaptcha;

    public function test_driver_falls_back_to_math_without_turnstile_keys(): void
    {
        config()->set('security.captcha.driver', null);
        config()->set('security.captcha.turnstile.site_key', null);
        config()->set('security.captcha.turnstile.secret_key', null);

        $this->assertSame(CaptchaService::DRIVER_MATH, app(CaptchaService::class)->driver());
    }

    public function test_driver_is_turnstile_once_both_keys_are_present(): void
    {
        config()->set('security.captcha.driver', null);
        $this->useTurnstile();

        $this->assertTrue(app(CaptchaService::class)->usesTurnstile());
    }

    public function test_a_half_configured_turnstile_does_not_lock_everyone_out(): void
    {
        // A site key with no secret is the shape of a botched deploy. Resolving
        // to turnstile there would render an unverifiable widget and make every
        // form permanently unsubmittable.
        config()->set('security.captcha.driver', null);
        config()->set('security.captcha.turnstile.site_key', 'only-the-site-key');
        config()->set('security.captcha.turnstile.secret_key', null);

        $this->assertSame(CaptchaService::DRIVER_MATH, app(CaptchaService::class)->driver());
    }

    public function test_turnstile_accepts_a_token_cloudflare_approves(): void
    {
        $this->useTurnstile();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $this->assertTrue(app(CaptchaService::class)->verify('a-good-token'));
    }

    public function test_turnstile_rejects_a_token_cloudflare_declines(): void
    {
        $this->useTurnstile();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ]),
        ]);

        $this->assertFalse(app(CaptchaService::class)->verify('a-bad-token'));
    }

    public function test_turnstile_rejects_an_empty_token_without_calling_cloudflare(): void
    {
        $this->useTurnstile();

        Http::fake();

        $this->assertFalse(app(CaptchaService::class)->verify(''));

        Http::assertNothingSent();
    }

    public function test_turnstile_fails_closed_when_siteverify_is_unreachable(): void
    {
        $this->useTurnstile();

        Http::fake(function (): void {
            throw new \Illuminate\Http\Client\ConnectionException('network is down');
        });

        // Failing OPEN here would drop the challenge for everyone at precisely
        // the moment we cannot check anything.
        $this->assertFalse(app(CaptchaService::class)->verify('any-token'));
    }

    public function test_turnstile_fails_closed_on_an_unexpected_status(): void
    {
        $this->useTurnstile();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response('gateway timeout', 504),
        ]);

        $this->assertFalse(app(CaptchaService::class)->verify('any-token'));
    }

    public function test_math_challenges_for_different_forms_do_not_clobber_each_other(): void
    {
        $captcha = app(CaptchaService::class);

        $captcha->generateMathChallenge('login');
        $loginAnswer = session(CaptchaService::SESSION_NAMESPACE.'.login');

        // The old component wrote one shared session key on every render, so
        // opening a second form invalidated the first tab's answer.
        $captcha->generateMathChallenge('contact');

        $this->assertSame($loginAnswer, session(CaptchaService::SESSION_NAMESPACE.'.login'));
    }

    public function test_a_math_answer_cannot_be_replayed(): void
    {
        $captcha = app(CaptchaService::class);

        $captcha->generateMathChallenge('login');
        $answer = $this->captchaAnswerFor('login');

        $this->assertTrue($captcha->verify($answer, 'login'));
        $this->assertFalse($captcha->verify($answer, 'login'));
    }

    public function test_a_pending_math_challenge_survives_a_re_render(): void
    {
        // Livewire re-renders on every wire:model keystroke. A generator that
        // produced a new sum each render would change the answer under the
        // visitor between reading the question and submitting it.
        $captcha = app(CaptchaService::class);

        $first = $captcha->generateMathChallenge('login');
        $answer = $this->captchaAnswerFor('login');

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($first, $captcha->generateMathChallenge('login'));
        }

        $this->assertSame($answer, $this->captchaAnswerFor('login'));
        $this->assertTrue($captcha->verify($answer, 'login'));
    }

    public function test_a_new_challenge_is_issued_once_the_previous_one_is_spent(): void
    {
        $captcha = app(CaptchaService::class);

        $captcha->generateMathChallenge('login');
        $answer = $this->captchaAnswerFor('login');

        $captcha->verify($answer, 'login');

        $captcha->generateMathChallenge('login');

        $this->assertNotSame('', $this->captchaAnswerFor('login'));
        $this->assertFalse($captcha->verify($answer, 'login') && $answer === $this->captchaAnswerFor('login'));
    }

    public function test_login_is_rejected_when_turnstile_declines(): void
    {
        $this->useTurnstile();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false]),
        ]);

        $user = User::factory()->create();

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->set('captcha', 'a-token-cloudflare-hates')
            ->call('login')
            ->assertHasErrors('captcha');

        $this->assertGuest();
    }

    public function test_login_succeeds_when_turnstile_approves(): void
    {
        $this->useTurnstile();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $user = User::factory()->create();

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->set('captcha', 'a-token-cloudflare-likes')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_a_filled_honeypot_blocks_the_contact_form(): void
    {
        Volt::test('pages.contact')
            ->set('name', 'Spam Bot')
            ->set('email', 'bot@example.com')
            ->set('subject', 'Cheap watches')
            ->set('message', 'Buy our cheap watches today, they are very cheap and also very good.')
            ->set('captcha', $this->captchaAnswerFor('contact'))
            ->set('website', 'http://spam.example.com')
            ->call('send')
            ->assertHasErrors('website');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_the_honeypot_message_does_not_name_the_trap(): void
    {
        // Telling a scraper's operator which field to leave blank defeats it.
        $rule = new \App\Rules\Honeypot;

        $message = null;
        $rule->validate('website', 'filled-in', function ($m) use (&$message): void {
            $message = $m;
        });

        $this->assertNotNull($message);
        $this->assertStringNotContainsStringIgnoringCase('honeypot', $message);
        $this->assertStringNotContainsStringIgnoringCase('website', $message);
    }
}
