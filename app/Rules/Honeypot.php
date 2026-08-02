<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The honeypot field is invisible to people, so anything in it came from a bot
 * that filled every input it found.
 *
 * The message is deliberately generic: naming the trap teaches the operator of
 * a scraper exactly which field to leave alone.
 */
class Honeypot implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (filled($value)) {
            $fail('Your submission could not be processed. Please try again.');
        }
    }
}
