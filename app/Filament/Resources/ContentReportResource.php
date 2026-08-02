<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContentReportResource\Pages;
use App\Models\ContentReport;
use App\Services\ContentModerationService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The moderation queue.
 *
 * Google Play's UGC policy wants evidence that reports go somewhere, so this is
 * as much a compliance artefact as an admin convenience.
 */
class ContentReportResource extends Resource
{
    protected static ?string $model = ContentReport::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-flag';

    protected static \UnitEnum|string|null $navigationGroup = 'Moderation';

    protected static ?string $navigationLabel = 'Content Reports';

    protected static ?string $modelLabel = 'content report';

    protected static ?string $pluralModelLabel = 'content reports';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        // Reports come from users, never from the panel.
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * Badge the nav with the pending count, so a queue nobody is watching still
     * announces itself.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = ContentReport::pending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $urgent = ContentReport::pending()
            ->whereIn('reason', ContentReport::urgentReasons())
            ->exists();

        return $urgent ? 'danger' : 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            // Urgent first, then oldest-pending: the thing most at risk of being
            // forgotten sits at the top.
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['reportable', 'reporter', 'reviewer']))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Reported')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (ContentReport $record): string => $record->reasonLabel())
                    ->color(fn (ContentReport $record): string => $record->isUrgent() ? 'danger' : 'warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reportable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),

                Tables\Columns\TextColumn::make('reportable')
                    ->label('Content')
                    ->formatStateUsing(fn (ContentReport $record): string => $record->reportable?->reportableLabel() ?? '(deleted)')
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('Reported by')
                    ->default('Anonymous')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ContentReport::STATUS_PENDING => 'warning',
                        ContentReport::STATUS_UPHELD => 'danger',
                        ContentReport::STATUS_REJECTED => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('action_taken')
                    ->label('Action')
                    ->badge()
                    ->default('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        ContentReport::STATUS_PENDING => 'Pending',
                        ContentReport::STATUS_UPHELD => 'Upheld',
                        ContentReport::STATUS_REJECTED => 'Rejected',
                    ])
                    ->default(ContentReport::STATUS_PENDING),

                Tables\Filters\SelectFilter::make('reason')
                    ->options(ContentReport::reasons()),

                Tables\Filters\Filter::make('urgent')
                    ->label('Urgent only')
                    ->query(fn (Builder $query): Builder => $query->whereIn('reason', ContentReport::urgentReasons())),
            ])
            ->recordActions([
                Actions\Action::make('uphold')
                    ->label('Uphold')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn (ContentReport $record): bool => $record->status === ContentReport::STATUS_PENDING)
                    ->schema([
                        Forms\Components\Select::make('action')
                            ->label('What should happen to the content?')
                            ->options([
                                ContentReport::ACTION_UNPUBLISHED => 'Unpublish (reversible)',
                                ContentReport::ACTION_DELETED => 'Delete',
                                ContentReport::ACTION_NONE => 'Leave it up',
                            ])
                            ->default(ContentReport::ACTION_UNPUBLISHED)
                            ->required(),

                        Forms\Components\Textarea::make('note')
                            ->label('Note for the record')
                            ->rows(3),
                    ])
                    ->action(function (ContentReport $record, array $data): void {
                        app(ContentModerationService::class)->resolve(
                            $record,
                            ContentReport::STATUS_UPHELD,
                            auth()->user(),
                            $data['note'] ?? null,
                            $data['action'],
                        );

                        Notification::make()
                            ->title('Report upheld')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-hand-thumb-up')
                    ->color('success')
                    ->visible(fn (ContentReport $record): bool => $record->status === ContentReport::STATUS_PENDING)
                    ->schema([
                        Forms\Components\Textarea::make('note')
                            ->label('Why is this not a violation?')
                            ->rows(3),
                    ])
                    ->action(function (ContentReport $record, array $data): void {
                        // Rejecting restores anything the auto-hide took down —
                        // otherwise a false report silently costs an artist
                        // their track for good.
                        $content = $record->reportable;

                        if ($content && array_key_exists('is_published', $content->getAttributes())) {
                            $content->forceFill(['is_published' => true])->save();
                        }

                        app(ContentModerationService::class)->resolve(
                            $record,
                            ContentReport::STATUS_REJECTED,
                            auth()->user(),
                            $data['note'] ?? null,
                            ContentReport::ACTION_NONE,
                        );

                        Notification::make()
                            ->title('Report rejected and content restored')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Placeholder::make('reason_label')
                ->label('Reason')
                ->content(fn (ContentReport $record): string => $record->reasonLabel()),

            Forms\Components\Placeholder::make('content_label')
                ->label('Content')
                ->content(fn (ContentReport $record): string => $record->reportable?->reportableLabel() ?? '(deleted)'),

            Forms\Components\Placeholder::make('details_text')
                ->label('Reporter notes')
                ->content(fn (ContentReport $record): string => $record->details ?: '—'),

            Forms\Components\Textarea::make('moderator_note')
                ->label('Moderator note')
                ->rows(4),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContentReports::route('/'),
            'edit' => Pages\EditContentReport::route('/{record}/edit'),
        ];
    }
}
