<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages;
use App\Mail\ContactMessageReplyMail;
use App\Models\ContactMessage;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope';

    protected static \UnitEnum|string|null $navigationGroup = 'Communication';

    protected static ?string $navigationLabel = 'Contact Center';

    protected static ?string $modelLabel = 'contact message';

    protected static ?string $pluralModelLabel = 'contact messages';

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
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
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
        $count = static::getModel()::query()
            ->active()
            ->unread()
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::getModel()::query()->active()->unread()->exists() ? 'warning' : 'gray';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'assignedAdmin', 'replies.user']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Message Details')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('From')
                        ->disabled(),
                    Forms\Components\TextInput::make('email')
                        ->label('Reply Email')
                        ->disabled(),
                    Forms\Components\TextInput::make('subject')
                        ->disabled(),
                    Forms\Components\Placeholder::make('received_at')
                        ->label('Received')
                        ->content(fn (?ContactMessage $record): string => $record?->created_at?->format('d M Y, H:i') ?? 'Unknown'),
                    Forms\Components\Placeholder::make('source')
                        ->label('Source')
                        ->content(fn (?ContactMessage $record): string => str($record?->source ?? 'contact_form')->replace('_', ' ')->title()->toString()),
                    Forms\Components\Placeholder::make('sender_account')
                        ->label('Account')
                        ->content(fn (?ContactMessage $record): string => $record?->user?->email ?? 'Guest visitor'),
                    Forms\Components\Textarea::make('message')
                        ->rows(10)
                        ->disabled()
                        ->columnSpanFull(),
                ]),
            Section::make('Inbox Workflow')
                ->description('Keep the message organized, assign ownership, and leave internal notes for the team.')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options(ContactMessage::statusOptions())
                        ->required(),
                    Forms\Components\Select::make('priority')
                        ->options(ContactMessage::priorityOptions())
                        ->required(),
                    Forms\Components\Select::make('assigned_admin_id')
                        ->label('Assigned Admin')
                        ->options(static::adminOptions())
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    Forms\Components\Textarea::make('admin_notes')
                        ->rows(6)
                        ->placeholder('Internal notes, follow-up context, or reminders for the admin team...')
                        ->columnSpanFull(),
                ]),
            Section::make('Reply History')
                ->description('Every reply sent from the contact center is stored here.')
                ->schema([
                    Forms\Components\Placeholder::make('reply_history')
                        ->hiddenLabel()
                        ->content(fn (?ContactMessage $record): HtmlString => static::replyHistoryContent($record)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subject')
                    ->searchable()
                    ->sortable()
                    ->limit(44),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ContactMessage::STATUS_NEW => 'warning',
                        ContactMessage::STATUS_OPEN => 'info',
                        ContactMessage::STATUS_REPLIED => 'success',
                        ContactMessage::STATUS_ARCHIVED => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ContactMessage::statusOptions()[$state] ?? Str::headline($state)),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ContactMessage::PRIORITY_URGENT => 'danger',
                        ContactMessage::PRIORITY_HIGH => 'warning',
                        ContactMessage::PRIORITY_NORMAL => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ContactMessage::priorityOptions()[$state] ?? Str::headline($state)),
                Tables\Columns\TextColumn::make('read_state')
                    ->label('State')
                    ->badge()
                    ->state(fn (ContactMessage $record): string => $record->read_at ? 'Read' : 'Unread')
                    ->color(fn (ContactMessage $record): string => $record->read_at ? 'gray' : 'primary'),
                Tables\Columns\TextColumn::make('assignedAdmin.name')
                    ->label('Assigned')
                    ->placeholder('Unassigned')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reply_count')
                    ->label('Replies')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_replied_at')
                    ->label('Last Reply')
                    ->since()
                    ->placeholder('No reply yet')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ContactMessage::statusOptions()),
                Tables\Filters\SelectFilter::make('priority')
                    ->options(ContactMessage::priorityOptions()),
                Tables\Filters\SelectFilter::make('assigned_admin_id')
                    ->label('Assigned Admin')
                    ->options(static::adminOptions()),
                Tables\Filters\Filter::make('unread')
                    ->label('Unread only')
                    ->query(fn (Builder $query): Builder => $query->unread()),
                Tables\Filters\Filter::make('mine')
                    ->label('Assigned to me')
                    ->query(fn (Builder $query): Builder => $query->where('assigned_admin_id', auth()->id())),
                Tables\Filters\Filter::make('archived')
                    ->label('Archived')
                    ->query(fn (Builder $query): Builder => $query->archived()),
            ])
            ->actions([
                Actions\EditAction::make()->label('Open'),
                static::replyAction(),
                static::markAsReadAction(),
                static::archiveAction(),
                static::restoreAction(),
                Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('markRead')
                        ->label('Mark selected as read')
                        ->icon('heroicon-o-eye')
                        ->action(function ($records): void {
                            $records->each->markAsRead();

                            Notification::make()
                                ->title('Messages marked as read')
                                ->success()
                                ->send();
                        }),
                    Actions\BulkAction::make('archiveSelected')
                        ->label('Archive selected')
                        ->icon('heroicon-o-archive-box')
                        ->action(function ($records): void {
                            $records->each->archive();

                            Notification::make()
                                ->title('Messages archived')
                                ->success()
                                ->send();
                        }),
                    Actions\BulkAction::make('restoreSelected')
                        ->label('Restore selected')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function ($records): void {
                            $records->each->unarchive();

                            Notification::make()
                                ->title('Messages restored')
                                ->success()
                                ->send();
                        }),
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactMessages::route('/'),
            'edit' => Pages\EditContactMessage::route('/{record}/edit'),
        ];
    }

    public static function replyAction(): Actions\Action
    {
        return Actions\Action::make('reply')
            ->label('Reply')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->modalHeading('Send a reply')
            ->modalDescription('Reply directly from the admin contact center. The sender will receive this by email.')
            ->modalWidth('2xl')
            ->fillForm(fn (ContactMessage $record): array => [
                'subject' => static::replySubject($record),
            ])
            ->schema([
                Forms\Components\TextInput::make('subject')
                    ->required()
                    ->maxLength(150),
                Forms\Components\Textarea::make('body')
                    ->label('Reply')
                    ->required()
                    ->rows(10)
                    ->maxLength(4000)
                    ->placeholder('Write a clear reply to the sender...'),
            ])
            ->visible(fn (ContactMessage $record): bool => ! $record->is_archived)
            ->action(function (ContactMessage $record, array $data): void {
                $admin = auth()->user();

                Mail::to($record->email)->send(new ContactMessageReplyMail(
                    contactMessage: $record,
                    subjectLine: $data['subject'],
                    body: $data['body'],
                    senderName: $admin?->name ?? config('mail.from.name', 'GrinMuzik'),
                ));

                $record->recordReply($admin, $data['subject'], $data['body']);

                Notification::make()
                    ->title('Reply sent')
                    ->body('The sender has been emailed and the reply is now stored in the contact center.')
                    ->success()
                    ->send();
            });
    }

    public static function markAsReadAction(): Actions\Action
    {
        return Actions\Action::make('markAsRead')
            ->label('Mark read')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->visible(fn (ContactMessage $record): bool => $record->read_at === null)
            ->action(function (ContactMessage $record): void {
                $record->markAsRead();

                Notification::make()
                    ->title('Marked as read')
                    ->success()
                    ->send();
            });
    }

    public static function archiveAction(): Actions\Action
    {
        return Actions\Action::make('archive')
            ->label('Archive')
            ->icon('heroicon-o-archive-box')
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (ContactMessage $record): bool => ! $record->is_archived)
            ->action(function (ContactMessage $record): void {
                $record->archive();

                Notification::make()
                    ->title('Message archived')
                    ->success()
                    ->send();
            });
    }

    public static function restoreAction(): Actions\Action
    {
        return Actions\Action::make('restore')
            ->label('Restore')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn (ContactMessage $record): bool => $record->is_archived)
            ->action(function (ContactMessage $record): void {
                $record->unarchive();

                Notification::make()
                    ->title('Message restored')
                    ->success()
                    ->send();
            });
    }

    protected static function adminOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query): Builder => $query->whereIn('name', ['admin', 'super_admin']))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function replySubject(ContactMessage $record): string
    {
        return Str::startsWith($record->subject, 'Re:') ? $record->subject : 'Re: ' . $record->subject;
    }

    protected static function replyHistoryContent(?ContactMessage $record): HtmlString
    {
        if (! $record) {
            return new HtmlString('');
        }

        $record->loadMissing('replies.user');

        if ($record->replies->isEmpty()) {
            return new HtmlString(
                '<div class="rounded-2xl border border-dashed border-gray-200 bg-white/70 px-5 py-6 text-sm text-gray-500">No replies have been sent from the contact center yet.</div>'
            );
        }

        $items = $record->replies
            ->map(function ($reply): string {
                $sender = e($reply->user?->name ?? 'Admin');
                $sentAt = e($reply->sent_at?->format('d M Y, H:i') ?? '');
                $subject = e($reply->subject);
                $body = nl2br(e($reply->body));

                return <<<HTML
                    <article class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{$subject}</p>
                                <p class="mt-1 text-xs font-medium uppercase tracking-[0.18em] text-primary/70">Sent by {$sender}</p>
                            </div>
                            <span class="text-xs text-slate-400">{$sentAt}</span>
                        </div>
                        <div class="mt-3 text-sm leading-6 text-slate-600">{$body}</div>
                    </article>
                HTML;
            })
            ->implode('');

        return new HtmlString('<div class="space-y-3">' . $items . '</div>');
    }
}
