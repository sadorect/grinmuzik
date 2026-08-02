<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single anti-abuse challenge used by every public form.
 *
 * Callers never branch on which challenge is active: they render
 * <x-captcha-field> and validate with the Captcha rule. This class decides
 * whether that means a Turnstile token or the legacy math answer.
 */
class CaptchaService
{
    public const DRIVER_TURNSTILE = 'turnstile';

    public const DRIVER_MATH = 'math';

    /** Legacy scalar key, kept so existing callers and tests keep working. */
    public const SESSION_KEY = 'captcha_answer';

    /** Per-form namespace. Deliberately NOT a child of SESSION_KEY — writing a
     *  scalar to "captcha_answer" would otherwise wipe "captcha_answer.<form>". */
    public const SESSION_NAMESPACE = 'captcha_answers';

    public function enabled(): bool
    {
        $configured = config('security.captcha.enabled');

        return $configured === null ? true : (bool) $configured;
    }

    /**
     * Turnstile whenever it is configured, and the legacy math challenge
     * otherwise, so an environment without keys (local, CI) still has a
     * solvable form rather than an unsolvable one.
     */
    public function driver(): string
    {
        $configured = config('security.captcha.driver');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $this->turnstileConfigured() ? self::DRIVER_TURNSTILE : self::DRIVER_MATH;
    }

    public function usesTurnstile(): bool
    {
        return $this->driver() === self::DRIVER_TURNSTILE;
    }

    public function siteKey(): string
    {
        return (string) config('security.captcha.turnstile.site_key');
    }

    /**
     * User-facing copy for a failed challenge. Turnstile has nothing to type,
     * so telling the visitor their "answer" was wrong would be nonsense.
     */
    public function failureMessage(): string
    {
        return $this->usesTurnstile()
            ? 'The security check could not be completed. Please wait a moment and try again.'
            : 'The security answer is incorrect. Please try again.';
    }

    protected function turnstileConfigured(): bool
    {
        return filled(config('security.captcha.turnstile.site_key'))
            && filled(config('security.captcha.turnstile.secret_key'));
    }

    /**
     * Verify a challenge response.
     *
     * $input is the typed number under the math driver and the
     * cf-turnstile-response token under Turnstile. Callers do not care which.
     */
    public function verify(?string $input, string $formKey = 'default'): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        return $this->usesTurnstile()
            ? $this->verifyTurnstile((string) $input)
            : $this->verifyMathAnswer((string) $input, $formKey);
    }

    /**
     * Exchange a Turnstile token with Cloudflare's siteverify endpoint.
     *
     * Tokens are single-use and expire after ~5 minutes; Cloudflare enforces
     * both, so a replayed token comes back invalid without extra work here.
     *
     * A network failure fails CLOSED. The site is served exclusively through
     * Cloudflare, so an unreachable siteverify almost certainly means visitors
     * cannot obtain a token either — failing open would only widen the hole at
     * the exact moment we cannot check anything.
     */
    protected function verifyTurnstile(string $token): bool
    {
        $token = trim($token);

        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('security.captcha.turnstile.timeout', 5))
                ->post((string) config('security.captcha.turnstile.verify_url'), [
                    'secret' => (string) config('security.captcha.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => request()->ip(),
                ]);
        } catch (Throwable $e) {
            Log::error('Turnstile verification request failed.', ['message' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            Log::error('Turnstile verification returned an unexpected status.', [
                'status' => $response->status(),
            ]);

            return false;
        }

        if ($response->json('success') === true) {
            return true;
        }

        $errors = (array) $response->json('error-codes', []);

        // Operator-side faults (bad or missing secret) are logged loudly — they
        // would otherwise look identical to a visitor failing the challenge.
        if (array_intersect($errors, ['invalid-input-secret', 'missing-input-secret'])) {
            Log::error('Turnstile secret key is missing or invalid.', ['error_codes' => $errors]);
        }

        return false;
    }

    /**
     * The math challenge for a form, generating one only if none is pending.
     *
     * Stability matters more than it looks. These are Livewire forms, and every
     * keystroke bound with wire:model re-renders the component — so a generator
     * that produced a new sum on each render would silently change the answer
     * under the visitor between reading the question and submitting it. That was
     * the defect in the component this replaces, and it is why the question is
     * stored alongside the answer rather than recomputed.
     *
     * Both are namespaced per form key, so two tabs (or the admin login beside
     * a public form) cannot clobber each other.
     */
    public function generateMathChallenge(string $formKey = 'default'): string
    {
        $pending = session($this->mathSessionKey($formKey));

        if (is_array($pending) && isset($pending['question'], $pending['answer'])) {
            return (string) $pending['question'];
        }

        return $this->refreshMathChallenge($formKey);
    }

    /**
     * Force a new math challenge, discarding any pending one.
     */
    public function refreshMathChallenge(string $formKey = 'default'): string
    {
        $a = random_int(2, 12);
        $b = random_int(1, 10);

        session([$this->mathSessionKey($formKey) => [
            'question' => "{$a} + {$b}",
            'answer' => $a + $b,
        ]]);

        return "{$a} + {$b}";
    }

    /**
     * Verify a math answer and clear it, so one challenge is good for exactly
     * one submission.
     */
    protected function verifyMathAnswer(string $input, string $formKey = 'default'): bool
    {
        $pending = session($this->mathSessionKey($formKey));

        $expected = is_array($pending)
            ? ($pending['answer'] ?? null)
            : session(self::SESSION_KEY);

        session()->forget([$this->mathSessionKey($formKey), self::SESSION_KEY]);

        if ($expected === null || $input === '') {
            return false;
        }

        return (int) $input === (int) $expected;
    }

    protected function mathSessionKey(string $formKey): string
    {
        return self::SESSION_NAMESPACE.'.'.$formKey;
    }
}
