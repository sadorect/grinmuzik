<?php

namespace Tests\Concerns;

use App\Services\CaptchaService;

/**
 * Helpers for getting past the anti-abuse challenge in feature tests.
 *
 * Tests used to write session(['captcha_answer' => 6]) directly, which coupled
 * them to the storage layout and — worse — meant they never exercised the
 * challenge the component actually rendered. These read the real answer
 * instead, so a broken generator fails a test rather than sailing past one.
 */
trait SolvesCaptcha
{
    /**
     * The answer to the math challenge the component just generated.
     */
    protected function captchaAnswerFor(string $formKey = 'default'): string
    {
        $pending = session(CaptchaService::SESSION_NAMESPACE.'.'.$formKey);

        return (string) (is_array($pending) ? ($pending['answer'] ?? '') : '');
    }

    /**
     * Switch the app onto Turnstile for a test, with fake keys.
     *
     * Http::fake() still has to be set up by the caller — deciding whether
     * siteverify says yes or no is the point of most of these tests.
     */
    protected function useTurnstile(): void
    {
        config()->set('security.captcha.driver', CaptchaService::DRIVER_TURNSTILE);
        config()->set('security.captcha.turnstile.site_key', 'test-site-key');
        config()->set('security.captcha.turnstile.secret_key', 'test-secret-key');
    }

    /**
     * Turn the challenge off entirely, for tests about something else.
     */
    protected function withoutCaptcha(): void
    {
        config()->set('security.captcha.enabled', false);
    }
}
