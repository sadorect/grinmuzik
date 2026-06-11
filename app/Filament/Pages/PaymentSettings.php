<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\AdminPanelAccess;
use App\Support\CurrencyHelper;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

class PaymentSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Site Content';

    protected static ?string $navigationLabel = 'Payment Settings';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.payment-settings';

    protected ?SiteSetting $settings = null;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return AdminPanelAccess::allows('manage settings');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->settings = SiteSetting::current();
        $this->form->fill($this->settingsPayload());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->model(SiteSetting::class)
            ->components([
                Section::make('Payments Master Switch')
                    ->description('Globally enable or disable all donation and payment flows. When off, all tracks behave as free downloads and donation-specific controls are hidden from artists and listeners. Existing pricing data is preserved.')
                    ->schema([
                        Forms\Components\Toggle::make('payments_enabled')
                            ->label('Enable donations and paid unlocks')
                            ->helperText('Turning this off disables payment gating, gift buttons, and Stripe Connect onboarding site-wide.')
                            ->default(false),
                    ])
                    ->visible(fn (): bool => SiteSetting::supportsMonetizationControls()),

                Section::make('Currency')
                    ->description('Set the platform-wide default currency. Donors pay in this currency unless they select an alternative. Artists inherit this as their payout currency until they set their own preference in their profile.')
                    ->schema([
                        Forms\Components\Select::make('platform_currency')
                            ->label('Platform default currency')
                            ->options(CurrencyHelper::SUPPORTED)
                            ->default('NGN')
                            ->searchable()
                            ->required()
                            ->helperText('This is the fallback currency for all payment flows. NGN is the platform default.'),
                    ])
                    ->visible(fn (): bool => SiteSetting::supportsCurrencyControls()),

                Section::make('Platform Fee')
                    ->description('The percentage the platform retains from each donation processed through a Stripe Connect destination charge. Only applies when an artist has an active Connect payout account. Direct charges (artists without Connect) are not affected.')
                    ->schema([
                        Forms\Components\TextInput::make('platform_fee_percent')
                            ->label('Platform fee (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.5)
                            ->default(5.0)
                            ->suffix('%')
                            ->required()
                            ->helperText('Example: 5 means the platform keeps 5% and the artist receives 95% of each donation. Set to 0 to pass the full amount to the artist.'),
                    ])
                    ->visible(fn (): bool => SiteSetting::supportsPaymentFeeControl()),

                Section::make('Per-Artist Currency Overrides')
                    ->description('Individual artist payout currencies and Stripe Connect account statuses are managed per-artist in the Artist Profiles resource. Use the admin override field there to force a specific currency for any artist, regardless of their own preference.')
                    ->schema([
                        Forms\Components\Placeholder::make('artist_overrides_note')
                            ->label('')
                            ->content('Go to Music Catalog → Artist Profiles → edit any artist → Payments & Payouts section to set per-artist currency or Connect status overrides.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $settings = $this->getSettings();
        $data = [];

        if (SiteSetting::supportsMonetizationControls()) {
            $data = array_merge($data, Arr::only($this->data, ['payments_enabled']));
        }

        if (SiteSetting::supportsCurrencyControls()) {
            $data = array_merge($data, Arr::only($this->data, ['platform_currency']));
        }

        if (SiteSetting::supportsPaymentFeeControl()) {
            $data = array_merge($data, Arr::only($this->data, ['platform_fee_percent']));
        }

        $settings->forceFill($data)->save();

        $this->settings = $settings;
        $this->form->fill($this->settingsPayload());

        Notification::make()
            ->title('Payment settings saved')
            ->success()
            ->send();
    }

    protected function getSettings(): SiteSetting
    {
        return $this->settings ??= SiteSetting::current();
    }

    protected function settingsPayload(): array
    {
        $keys = [];

        if (SiteSetting::supportsMonetizationControls()) {
            $keys[] = 'payments_enabled';
        }

        if (SiteSetting::supportsCurrencyControls()) {
            $keys[] = 'platform_currency';
        }

        if (SiteSetting::supportsPaymentFeeControl()) {
            $keys[] = 'platform_fee_percent';
        }

        return $this->getSettings()->only($keys);
    }
}
