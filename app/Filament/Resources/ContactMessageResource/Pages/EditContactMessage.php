<?php

namespace App\Filament\Resources\ContactMessageResource\Pages;

use App\Filament\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContactMessage extends EditRecord
{
    protected static string $resource = ContactMessageResource::class;

    protected function afterFill(): void
    {
        $this->getRecord()->markAsRead();
        $this->refreshFormData(['status']);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === ContactMessage::STATUS_ARCHIVED) {
            $data['archived_at'] = $this->getRecord()->archived_at ?? now();
            $data['read_at'] = $this->getRecord()->read_at ?? now();
        } elseif ($this->getRecord()->archived_at !== null) {
            $data['archived_at'] = null;
        }

        if (($data['status'] ?? null) === ContactMessage::STATUS_NEW && $this->getRecord()->read_at !== null) {
            $data['status'] = ContactMessage::STATUS_OPEN;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ContactMessageResource::replyAction(),
            ContactMessageResource::archiveAction(),
            ContactMessageResource::restoreAction(),
            Actions\DeleteAction::make(),
        ];
    }
}
