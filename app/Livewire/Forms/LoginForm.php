<?php

namespace App\Livewire\Forms;

use App\Support\AuthThrottle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        AuthThrottle::ensureLoginAllowed($this->email);

        if (! Auth::attempt($this->only(['email', 'password']), $this->remember)) {
            AuthThrottle::recordFailedLogin($this->email);

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        AuthThrottle::clearLogin($this->email);
    }
}
