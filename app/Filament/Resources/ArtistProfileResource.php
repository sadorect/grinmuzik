<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\HasAdminResourcePermissions;
use App\Filament\Resources\ArtistProfileResource\Pages;
use App\Models\ArtistProfile;
use App\Models\SiteSetting;
use App\Support\AdminPanelAccess;
use App\Support\CurrencyHelper;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArtistProfileResource extends Resource
{
    use HasAdminResourcePermissions;

    protected static ?string $model = ArtistProfile::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-musical-note';

    protected static \UnitEnum|string|null $navigationGroup = 'Music Catalog';

    protected static array|string|null $viewAnyPermissions = [
        'view artists',
        'approve artists',
        'reject artists',
        'manage artists',
    ];

    protected static array|string|null $viewPermissions = [
        'view artists',
        'approve artists',
        'reject artists',
        'manage artists',
    ];

    protected static array|string|null $createPermissions = 'manage artists';

    protected static array|string|null $editPermissions = 'manage artists';

    protected static array|string|null $deletePermissions = 'manage artists';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Artist Info')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\TextInput::make('stage_name')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('slug')
                        ->maxLength(100)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Textarea::make('bio')
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),
            Section::make('Social Links')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('website')->url(),
                    Forms\Components\TextInput::make('twitter')->maxLength(200),
                    Forms\Components\TextInput::make('instagram')->maxLength(200),
                    Forms\Components\TextInput::make('youtube')->maxLength(200),
                    Forms\Components\TextInput::make('spotify')->maxLength(200),
                ]),
            Section::make('Status')
                ->columns(4)
                ->schema([
                    Forms\Components\Toggle::make('is_verified')->label('Verified'),
                    Forms\Components\Toggle::make('is_approved')->label('Approved'),
                    Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
                    Forms\Components\Toggle::make('is_featured')
                        ->label('Featured')
                        ->helperText('Prioritize this artist in discovery rails.')
                        ->visible(fn (): bool => ArtistProfile::supportsFeaturedCuration()),
                ]),
            Section::make('Payments & Payouts')
                ->description('Control per-artist currency settings and Stripe Connect status. The admin override takes precedence over the artist\'s own preference.')
                ->columns(2)
                ->collapsed()
                ->visible(fn (): bool => SiteSetting::supportsMonetizationControls())
                ->schema([
                    Forms\Components\TextInput::make('country_code')
                        ->label('Country code (ISO 2-letter)')
                        ->maxLength(2)
                        ->placeholder('NG')
                        ->helperText('Used when creating a Stripe Connect Express account for this artist.'),
                    Forms\Components\Select::make('preferred_currency')
                        ->label("Artist's preferred payout currency")
                        ->options(CurrencyHelper::SUPPORTED)
                        ->searchable()
                        ->nullable()
                        ->helperText('The currency this artist wants to receive donations in. Falls back to platform default if not set.'),
                    Forms\Components\Select::make('admin_currency_override')
                        ->label('Admin currency override')
                        ->options(CurrencyHelper::SUPPORTED)
                        ->searchable()
                        ->nullable()
                        ->helperText('When set, overrides the artist\'s own preference. Clear this field to let the artist\'s setting apply again.'),
                    Forms\Components\TextInput::make('stripe_connect_status')
                        ->label('Stripe Connect status')
                        ->disabled()
                        ->helperText('Read-only. Use "Sync Connect status" table action to refresh from Stripe.'),
                    Forms\Components\TextInput::make('stripe_connect_account_id')
                        ->label('Stripe Connect account ID')
                        ->disabled()
                        ->helperText('Read-only. Set automatically during artist onboarding.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('user')
                ->withFeaturedRecommendationMetrics())
            ->columns([
                Tables\Columns\TextColumn::make('stage_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('User')->searchable(),
                Tables\Columns\TextColumn::make('online_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (ArtistProfile $record): string => $record->user?->is_online ? 'Online now' : 'Offline')
                    ->color(fn (ArtistProfile $record): string => $record->user?->is_online ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('last_seen_status')
                    ->label('Last Seen')
                    ->state(fn (ArtistProfile $record): string => $record->user?->last_seen_at?->diffForHumans() ?? 'No recent activity'),
                Tables\Columns\TextColumn::make('public_listing_status')
                    ->label('Public Listing')
                    ->badge()
                    ->state(function (ArtistProfile $record): string {
                        if ($record->is_approved && $record->is_active && ((int) ($record->published_tracks_count ?? 0) === 0)) {
                            return 'In limbo';
                        }

                        if ($record->is_approved && $record->is_active) {
                            return 'Live';
                        }

                        if (! $record->is_approved) {
                            return 'Pending approval';
                        }

                        return 'Inactive';
                    })
                    ->color(function (ArtistProfile $record): string {
                        if ($record->is_approved && $record->is_active && ((int) ($record->published_tracks_count ?? 0) === 0)) {
                            return 'warning';
                        }

                        if ($record->is_approved && $record->is_active) {
                            return 'success';
                        }

                        if (! $record->is_approved) {
                            return 'gray';
                        }

                        return 'danger';
                    }),
                Tables\Columns\TextColumn::make('featured_recommendation_status')
                    ->label('Feature Recommendation')
                    ->badge()
                    ->state(fn (ArtistProfile $record): string => $record->featured_recommendation_status)
                    ->color(fn (ArtistProfile $record): string => match ($record->featured_recommendation_status) {
                        'Featured' => 'success',
                        'Recommended' => 'warning',
                        'Eligible' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('featured_recommendation_score')
                    ->label('Feature Score')
                    ->state(fn (ArtistProfile $record): int => $record->featured_recommendation_score),
                Tables\Columns\TextColumn::make('published_tracks_count')
                    ->label('Published Tracks')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_plays')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('followers_count')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('total_donations')
                    ->label('Total Donations')
                    ->state(fn (ArtistProfile $record): string => CurrencyHelper::format((float) $record->total_donations, $record->effectiveCurrency()))
                    ->sortable(),
                Tables\Columns\TextColumn::make('effective_currency')
                    ->label('Payout Currency')
                    ->state(fn (ArtistProfile $record): string => $record->effectiveCurrency())
                    ->badge()
                    ->visible(fn (): bool => SiteSetting::supportsMonetizationControls()),
                Tables\Columns\TextColumn::make('stripe_connect_status')
                    ->label('Connect')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'         => 'success',
                        'pending'        => 'warning',
                        'restricted'     => 'danger',
                        default          => 'gray',
                    })
                    ->visible(fn (): bool => SiteSetting::supportsMonetizationControls()),
                Tables\Columns\IconColumn::make('is_verified')->boolean()->label('Verified'),
                Tables\Columns\IconColumn::make('is_featured')->boolean()->label('Featured')->visible(ArtistProfile::supportsFeaturedCuration()),
                Tables\Columns\IconColumn::make('is_approved')->boolean()->label('Approved'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_approved'),
                Tables\Filters\TernaryFilter::make('is_verified'),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\Filter::make('online_now')
                    ->label('Online now')
                    ->query(fn (Builder $query): Builder => $query->whereHas('user', fn (Builder $userQuery): Builder => $userQuery->online())),
                Tables\Filters\Filter::make('in_limbo')
                    ->label('In limbo')
                    ->query(fn (Builder $query): Builder => $query->inLimbo()),
                Tables\Filters\Filter::make('feature_recommendation_candidates')
                    ->label('Feature candidates')
                    ->query(fn (Builder $query): Builder => $query->featuredRecommendationEligible()),
                ...(ArtistProfile::supportsFeaturedCuration()
                    ? [Tables\Filters\TernaryFilter::make('is_featured')]
                    : []),
            ])
            ->actions([
                Actions\Action::make('impersonate')
                    ->label('Sign in as artist')
                    ->icon('heroicon-o-arrow-right-end-on-rectangle')
                    ->color('warning')
                    ->visible(fn ($record): bool => app(\App\Support\Impersonation::class)->canImpersonate(\Illuminate\Support\Facades\Auth::user(), $record->user))
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record): string => 'Sign in as ' . trim((string) $record->user?->name) . '?')
                    ->modalDescription('You will see grinmuzik exactly as they do and can act for them. Every change is recorded in the audit log as yours. Use "Return to my account" in the banner, or Log out, to come back.')
                    ->modalSubmitActionLabel('Sign in as them')
                    ->action(function ($record) {
                        // start() re-checks every rule, so a stale button cannot bypass them.
                        app(\App\Support\Impersonation::class)->start(
                            \Illuminate\Support\Facades\Auth::user(),
                            $record->user,
                            static::getUrl('index'),
                        );

                        return redirect()->route('dashboard');
                    }),
                Actions\EditAction::make(),
                Actions\Action::make('verifyAndApprove')
                    ->label('Verify + Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ArtistProfile $record): bool => AdminPanelAccess::allows(['approve artists', 'manage artists']) && (
                        ! $record->is_verified ||
                        ! $record->is_approved ||
                        ! ($record->user?->hasVerifiedEmail() ?? false)
                    ))
                    ->action(function (ArtistProfile $record): void {
                        $record->loadMissing('user');
                        $record->user?->manuallyVerifyAndApproveArtist();
                    }),
                Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ArtistProfile $record): bool => AdminPanelAccess::allows(['approve artists', 'manage artists']) && (
                        ($record->user?->hasVerifiedEmail() ?? false) || $record->is_verified
                    ) && ! $record->is_approved)
                    ->action(function (ArtistProfile $record): void {
                        $record->forceFill([
                            'is_verified' => $record->is_verified || ($record->user?->hasVerifiedEmail() ?? false),
                            'is_approved' => true,
                            'is_active' => true,
                        ])->save();
                    }),
                Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (): bool => AdminPanelAccess::allows(['reject artists', 'manage artists']))
                    ->action(fn (ArtistProfile $record) => $record->rejectFromModeration()),
                Actions\Action::make('featureRecommendation')
                    ->label('Feature recommendation')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (ArtistProfile $record): bool => AdminPanelAccess::allows('manage artists') && $record->is_featured_recommended)
                    ->action(fn (ArtistProfile $record) => $record->update(['is_featured' => true])),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\BulkAction::make('verifyAndApproveSelected')
                        ->label('Verify + approve selected')
                        ->icon('heroicon-o-check-badge')
                        ->visible(fn (): bool => AdminPanelAccess::allows(['approve artists', 'manage artists']))
                        ->action(fn ($records) => $records->each(function (ArtistProfile $record): void {
                            $record->loadMissing('user');
                            $record->user?->manuallyVerifyAndApproveArtist();
                        })),
                    Actions\BulkAction::make('approveSelected')
                        ->label('Approve selected')
                        ->icon('heroicon-o-check-circle')
                        ->visible(fn (): bool => AdminPanelAccess::allows(['approve artists', 'manage artists']))
                        ->action(fn ($records) => $records->each(function (ArtistProfile $record): void {
                            $record->forceFill([
                                'is_verified' => $record->is_verified || ($record->user?->hasVerifiedEmail() ?? false),
                                'is_approved' => true,
                                'is_active' => true,
                            ])->save();
                        })),
                    Actions\BulkAction::make('rejectSelected')
                        ->label('Reject selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (): bool => AdminPanelAccess::allows(['reject artists', 'manage artists']))
                        ->action(fn ($records) => $records->each(fn (ArtistProfile $record) => $record->rejectFromModeration())),
                    ...(ArtistProfile::supportsFeaturedCuration()
                        ? [
                            Actions\BulkAction::make('feature')
                                ->label('Feature selected')
                                ->visible(fn (): bool => AdminPanelAccess::allows('manage artists'))
                                ->action(fn ($records) => $records->each->update(['is_featured' => true]))
                                ->icon('heroicon-o-star'),
                            Actions\BulkAction::make('unfeature')
                                ->label('Remove feature')
                                ->visible(fn (): bool => AdminPanelAccess::allows('manage artists'))
                                ->action(fn ($records) => $records->each->update(['is_featured' => false]))
                                ->icon('heroicon-o-star'),
                            Actions\BulkAction::make('featureRecommended')
                                ->label('Feature recommended selected')
                                ->visible(fn (): bool => AdminPanelAccess::allows('manage artists'))
                                ->action(fn ($records) => $records->each(function (ArtistProfile $record): void {
                                    if ($record->is_featured_recommended) {
                                        $record->update(['is_featured' => true]);
                                    }
                                }))
                                ->icon('heroicon-o-star'),
                        ]
                        : []),
                ]),
            ]);  
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListArtistProfiles::route('/'),
            'create' => Pages\CreateArtistProfile::route('/create'),
            'edit'   => Pages\EditArtistProfile::route('/{record}/edit'),
        ];
    }
}
