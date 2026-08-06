<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ViewActivityLog extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;

    public function getTitle(): string
    {
        $record = $this->getRecord();

        return $record instanceof ActivityLog
            ? $record->eventLabel()
            : 'Activity';
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('What happened')
                ->schema([
                    TextEntry::make('event')
                        ->label('Action')
                        ->formatStateUsing(fn (ActivityLog $record): string => $record->eventLabel()),
                    TextEntry::make('created_at')
                        ->label('When')
                        ->dateTime('d M Y H:i:s')
                        ->helperText(fn (ActivityLog $record): string => $record->created_at?->diffForHumans() ?? ''),
                    TextEntry::make('subject_label')
                        ->label('Subject')
                        ->placeholder('—')
                        // Says so explicitly, because a name here does not
                        // imply the record still exists.
                        ->helperText('Recorded at the time of the action; the record itself may since have been removed.'),
                    TextEntry::make('subject_type')
                        ->label('Subject type')
                        ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),
                ])
                ->columns(2),

            Section::make('Who did it')
                ->schema([
                    TextEntry::make('actor_label')
                        ->label('Name')
                        ->formatStateUsing(fn (ActivityLog $record): string => $record->actorName()),
                    TextEntry::make('actor.email')
                        ->label('Email')
                        ->placeholder('—'),
                    TextEntry::make('actor_type')
                        ->label('Acting as')
                        ->formatStateUsing(fn (?string $state): string => ActivityLog::actorTypeLabels()[$state] ?? (string) $state)
                        ->helperText('The role held at the time, not necessarily the role held now.'),
                    TextEntry::make('artistProfile.stage_name')
                        ->label('Artist affected')
                        ->placeholder('—'),
                    TextEntry::make('ip_address')
                        ->label('IP address')
                        ->placeholder('—')
                        ->copyable(),
                    TextEntry::make('user_agent')
                        ->label('User agent')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Changes')
                ->schema([
                    KeyValueEntry::make('properties')
                        ->label('')
                        ->keyLabel('Field')
                        ->valueLabel('Value')
                        ->state(fn (ActivityLog $record): array => static::presentProperties($record))
                        ->columnSpanFull(),
                ])
                ->visible(fn (ActivityLog $record): bool => filled($record->properties)),
        ]);
    }

    /**
     * Flatten the stored properties into readable "old → new" lines.
     *
     * @return array<string, string>
     */
    protected static function presentProperties(ActivityLog $record): array
    {
        $rows = [];

        foreach ($record->properties ?? [] as $field => $value) {
            $label = Str::headline($field);

            if (is_array($value) && array_key_exists('new', $value)) {
                $old = static::scalar($value['old'] ?? null);
                $new = static::scalar($value['new']);

                $rows[$label] = $old === '—' ? $new : "{$old} → {$new}";

                continue;
            }

            $rows[$label] = static::scalar($value);
        }

        return $rows;
    }

    protected static function scalar(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '—',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => json_encode($value) ?: '—',
            default => (string) $value,
        };
    }
}
