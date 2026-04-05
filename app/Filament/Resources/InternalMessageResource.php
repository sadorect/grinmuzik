<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InternalMessageResource\Pages;
use App\Jobs\SendInternalMessageJob;
use App\Models\InternalMessage;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InternalMessageResource extends Resource
{
    protected static ?string $model = InternalMessage::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static \UnitEnum|string|null $navigationGroup = 'Communication';

    protected static ?string $navigationLabel = 'Broadcast Messages';

    protected static ?string $modelLabel = 'message';

    protected static ?string $pluralModelLabel = 'broadcast messages';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        // Only drafts can be edited
        return (auth()->user()?->isAdmin() ?? false) && ! $record->isSent();
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->draft()->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Compose Message')
                ->schema([
                    Forms\Components\TextInput::make('subject')
                        ->required()
                        ->maxLength(255)
                        ->helperText('You may use {name}, {username}, {email} as placeholders.')
                        ->columnSpanFull(),

                    Forms\Components\RichEditor::make('body')
                        ->label('Message body')
                        ->required()
                        ->helperText('Personalise with {name}, {username}, or {email} — e.g. "Dear {name}, …"')
                        ->toolbarButtons([
                            'bold', 'italic', 'underline', 'strike',
                            'link', 'orderedList', 'bulletList',
                            'h2', 'h3', 'blockquote',
                        ])
                        ->columnSpanFull(),

                    Forms\Components\Select::make('recipient_user_id')
                        ->label('Send to a specific user (optional)')
                        ->placeholder('Leave blank to send by audience group')
                        ->relationship(
                            name: 'recipient',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn ($query) => $query->orderBy('name'),
                        )
                        ->searchable(['name', 'email'])
                        ->preload(false)
                        ->nullable()
                        ->helperText('When set, this message is delivered only to this user. The audience selector below is ignored.')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('audience')
                        ->label('Audience (broadcast)')
                        ->options([
                            'all'       => 'All users',
                            'artists'   => 'Artists only',
                            'listeners' => 'Listeners only',
                        ])
                        ->default('all')
                        ->required(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => blank($get('recipient_user_id')))
                        ->helperText('Only applies when no specific user is selected above.')
                        ->hidden(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => filled($get('recipient_user_id'))),

                    Forms\Components\Toggle::make('send_email')
                        ->label('Also send by email')
                        ->helperText('When sent, an email will be dispatched to the recipient(s).')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('subject')
                    ->searchable()
                    ->limit(60),

                Tables\Columns\BadgeColumn::make('audience')
                    ->label('Audience')
                    ->getStateUsing(fn (InternalMessage $record): string => $record->audienceLabel())
                    ->color(fn (InternalMessage $record): string => match(true) {
                        $record->recipient_user_id !== null => 'gray',
                        $record->audience === 'artists'     => 'info',
                        $record->audience === 'listeners'   => 'success',
                        default                             => 'primary',
                    }),

                Tables\Columns\IconColumn::make('send_email')
                    ->label('Email')
                    ->boolean(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn (InternalMessage $record): string => $record->isSent() ? 'Sent' : 'Draft')
                    ->color(fn (string $state): string => $state === 'Sent' ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent at')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('sender.name')
                    ->label('Created by')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('audience')
                    ->options([
                        'all'       => 'All users',
                        'artists'   => 'Artists',
                        'listeners' => 'Listeners',
                    ]),
                Tables\Filters\Filter::make('sent')
                    ->label('Sent only')
                    ->query(fn ($query) => $query->sent()),
                Tables\Filters\Filter::make('draft')
                    ->label('Drafts only')
                    ->query(fn ($query) => $query->draft()),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn (InternalMessage $record): bool => ! $record->isSent()),

                Actions\Action::make('send')
                    ->label('Send Now')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Send this message?')
                    ->modalDescription(fn (InternalMessage $record): string => "This will publish the message to inbox for {$record->audienceLabel()}" . ($record->send_email ? ' and send them an email.' : '.'))
                    ->visible(fn (InternalMessage $record): bool => ! $record->isSent())
                    ->action(function (InternalMessage $record): void {
                        $record->update([
                            'sent_at'    => now(),
                            'created_by' => auth()->id(),
                        ]);

                        if ($record->send_email) {
                            SendInternalMessageJob::dispatch($record);
                        }

                        Notification::make()
                            ->title('Message sent successfully')
                            ->success()
                            ->send();
                    }),

                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListInternalMessages::route('/'),
            'create' => Pages\CreateInternalMessage::route('/create'),
            'edit'   => Pages\EditInternalMessage::route('/{record}/edit'),
        ];
    }
}
