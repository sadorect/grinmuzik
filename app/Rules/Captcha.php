<?php

namespace App\Rules;

use App\Services\CaptchaService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates whichever challenge CaptchaService has active.
 *
 * Replaces App\Rules\CaptchaAnswer, which understood only the math challenge
 * and cleared the session answer even when an unrelated rule had failed the
 * request — forcing the visitor to re-solve a challenge they had got right.
 */
class Captcha implements ValidationRule
{
    public function __construct(
        protected string $formKey = 'default',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $captcha = app(CaptchaService::class);

        if (! $captcha->enabled()) {
            return;
        }

        if (! $captcha->verify(is_scalar($value) ? (string) $value : null, $this->formKey)) {
            $fail($captcha->failureMessage());
        }
    }
}
