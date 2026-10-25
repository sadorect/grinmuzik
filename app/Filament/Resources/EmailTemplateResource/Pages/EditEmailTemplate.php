<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use App\Mail\EmailTemplateTestMail;
use App\Models\EmailTemplate;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->definition()['name'];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sendTest')
                ->label('Send test to me')
                ->icon('heroicon-o-paper-airplane')
                ->modalDescription(fn () => 'Sends the saved wording, filled with sample data, to ' . auth()->user()->email . '. Save your changes first.')
                ->action(function (): void {
                    try {
                        Mail::to(auth()->user()->email)->send(new EmailTemplateTestMail($this->getRecord()->key, auth()->user()->name));
                        Notification::make()->title('Test email sent to ' . auth()->user()->email)->success()->send();
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->title('The test email could not be sent')->body($exception->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('resetToDefault')
                ->label('Reset to default')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Replaces this email\'s wording with the original default. Your edits will be lost.')
                ->visible(fn () => $this->getRecord()->isCustomised())
                ->action(function (): void {
                    /** @var EmailTemplate $record */
                    $record = $this->getRecord();
                    $record->resetToDefault();
                    $this->fillForm();
                    Notification::make()->title('Wording reset to the default')->success()->send();
                }),
        ];
    }
}
