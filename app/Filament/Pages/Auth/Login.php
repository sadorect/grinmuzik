<?php

namespace App\Filament\Pages\Auth;

use App\Services\CaptchaService;
use App\Support\AuthThrottle;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Admin panel login.
 *
 * This previously carried its own copy of the math challenge and no rate limit
 * at all, which made it the weakest door on the site while guarding the most.
 * It now shares CaptchaService with the public forms and is throttled per IP.
 */
class Login extends BaseLogin
{
    public string $captchaQuestion = '';

    protected const FORM_KEY = 'admin-login';

    public function mount(): void
    {
        parent::mount();

        $this->refreshChallenge();
    }

    protected function refreshChallenge(): void
    {
        $captcha = app(CaptchaService::class);

        if ($captcha->enabled() && ! $captcha->usesTurnstile()) {
            $this->captchaQuestion = $captcha->generateMathChallenge(self::FORM_KEY);
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
                ...$this->getCaptchaFormComponents(),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected function getCaptchaFormComponents(): array
    {
        $captcha = app(CaptchaService::class);

        if (! $captcha->enabled()) {
            return [];
        }

        if ($captcha->usesTurnstile()) {
            return [
                ViewField::make('captcha')
                    ->view('filament.forms.components.turnstile')
                    ->label(''),
            ];
        }

        return [
            TextInput::make('captcha')
                ->label(fn (): string => "Security check: what is {$this->captchaQuestion} ?")
                ->required()
                ->integer()
                ->inputMode('numeric')
                ->autocomplete('off')
                ->placeholder('Your answer'),
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        $captcha = app(CaptchaService::class);

        // Throttle before the credential check, so an attacker cannot keep
        // testing passwords indefinitely by failing the challenge each time.
        AuthThrottle::ensureAllowed('admin_login', 'data.email');

        if (! $captcha->verify((string) ($this->data['captcha'] ?? ''), self::FORM_KEY)) {
            $this->refreshChallenge();

            throw ValidationException::withMessages([
                'data.captcha' => $captcha->failureMessage(),
            ]);
        }

        return parent::authenticate();
    }
}
