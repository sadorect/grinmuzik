<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\HasAdminResourcePermissions;
use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Models\EmailTemplate;
use App\Support\Mail\EmailTemplateCatalog;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class EmailTemplateResource extends Resource
{
    use HasAdminResourcePermissions;

    protected static ?string $model = EmailTemplate::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope-open';

    protected static \UnitEnum|string|null $navigationGroup = 'Communication';

    protected static ?string $navigationLabel = 'Email Templates';

    protected static array|string|null $viewAnyPermissions = 'manage settings';

    protected static array|string|null $viewPermissions = 'manage settings';

    protected static array|string|null $editPermissions = 'manage settings';

    /** Templates come from the catalog; they are never created or deleted by hand. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $editable = fn (string $field) => fn (?EmailTemplate $record): bool => $record && in_array($field, EmailTemplateCatalog::fields($record->key), true);

        return $schema->schema([
            Section::make('About this email')
                ->schema([
                    Forms\Components\Placeholder::make('about')
                        ->hiddenLabel()
                        ->content(fn (?EmailTemplate $record) => $record ? new HtmlString(
                            '<p class="text-sm">' . e($record->definition()['description']) . '</p>'
                            . '<p class="mt-1 text-sm text-gray-500">Sent to: ' . e($record->definition()['audience']) . '</p>'
                        ) : null),
                ]),
            Section::make('Wording')
                ->description('Use the placeholders listed below in any field, e.g. {track_title}. In the body, separate paragraphs with a blank line; a paragraph whose placeholder has no value for a particular email is left out.')
                ->schema([
                    Forms\Components\TextInput::make('subject')->required()->maxLength(255)->visible($editable('subject')),
                    Forms\Components\TextInput::make('preheader')
                        ->label('Preview text')
                        ->helperText('Shown after the subject in most inboxes.')
                        ->maxLength(255)
                        ->visible($editable('preheader')),
                    Forms\Components\TextInput::make('eyebrow')
                        ->label('Eyebrow')
                        ->helperText('The small label above the title.')
                        ->maxLength(255)
                        ->visible($editable('eyebrow')),
                    Forms\Components\TextInput::make('title')->required()->maxLength(255)->visible($editable('title')),
                    Forms\Components\Textarea::make('intro')
                        ->label('Intro')
                        ->rows(2)
                        ->visible($editable('intro')),
                    Forms\Components\Textarea::make('body')
                        ->rows(8)
                        ->visible($editable('body')),
                    Forms\Components\TextInput::make('cta_label')
                        ->label('Button text')
                        ->maxLength(255)
                        ->visible($editable('cta_label')),
                    Forms\Components\Textarea::make('footer_note')
                        ->label('Footer note')
                        ->rows(2)
                        ->visible($editable('footer_note')),
                ]),
            Section::make('Placeholders')
                ->collapsible()
                ->schema([
                    Forms\Components\Placeholder::make('placeholders')
                        ->hiddenLabel()
                        ->content(function (?EmailTemplate $record): ?HtmlString {
                            if (! $record) {
                                return null;
                            }

                            $items = collect($record->definition()['placeholders'] + EmailTemplateCatalog::GLOBAL_PLACEHOLDERS)
                                ->map(fn (string $description, string $name) => '<li><code>{' . e($name) . '}</code> — ' . e($description) . '</li>')
                                ->implode('');

                            return new HtmlString('<ul class="space-y-1 text-sm">' . $items . '</ul>');
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Email')
                    ->state(fn (EmailTemplate $record) => $record->definition()['name'])
                    ->description(fn (EmailTemplate $record) => $record->definition()['description'])
                    ->wrap(),
                Tables\Columns\TextColumn::make('group')
                    ->state(fn (EmailTemplate $record) => $record->definition()['group'])
                    ->badge(),
                Tables\Columns\TextColumn::make('audience')
                    ->label('Sent to')
                    ->state(fn (EmailTemplate $record) => $record->definition()['audience']),
                Tables\Columns\IconColumn::make('customised')
                    ->label('Edited')
                    ->state(fn (EmailTemplate $record) => $record->isCustomised())
                    ->boolean()
                    ->trueIcon('heroicon-o-pencil-square')
                    ->falseIcon('heroicon-o-minus')
                    ->tooltip(fn (EmailTemplate $record) => $record->isCustomised() ? 'Wording changed from the default' : 'Default wording'),
                Tables\Columns\TextColumn::make('updated_at')->label('Last saved')->since()->sortable(),
            ])
            ->defaultSort('id')
            ->paginated(false)
            ->actions([Actions\EditAction::make()]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Only templates the code still knows about.
        return parent::getEloquentQuery()->whereIn('key', array_keys(EmailTemplateCatalog::all()));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTemplates::route('/'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
