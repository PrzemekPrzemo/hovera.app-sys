<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources\SpecialistThreadResource\Pages;

use App\Filament\Owner\Resources\SpecialistThreadResource;
use App\Models\Central\ExternalSpecialist;
use App\Models\Central\OwnerSpecialistMessage;
use App\Services\Specialist\OwnerSpecialistMessagingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateSpecialistThread extends CreateRecord
{
    protected static string $resource = SpecialistThreadResource::class;

    /**
     * @param array<string,mixed> $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $owner = Auth::user();
        if ($owner === null) {
            throw ValidationException::withMessages([
                'specialist_id' => __('owner/specialist_thread.error.no_context'),
            ]);
        }

        $specialist = ExternalSpecialist::query()->findOrFail($data['specialist_id']);

        return app(OwnerSpecialistMessagingService::class)->startThread(
            owner: $owner,
            specialist: $specialist,
            subject: (string) $data['subject'],
            senderType: OwnerSpecialistMessage::SENDER_OWNER,
            senderId: (string) $owner->id,
            body: (string) ($this->data['body'] ?? ''),
            horseId: $data['horse_id'] ?? null,
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
