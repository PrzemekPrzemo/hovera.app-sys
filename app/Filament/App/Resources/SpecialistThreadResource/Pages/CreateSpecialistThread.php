<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\SpecialistThreadResource\Pages;

use App\Filament\App\Resources\SpecialistThreadResource;
use App\Models\Central\ExternalSpecialist;
use App\Models\Central\SpecialistMessage;
use App\Services\Specialist\SpecialistMessagingService;
use App\Tenancy\TenantManager;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateSpecialistThread extends CreateRecord
{
    protected static string $resource = SpecialistThreadResource::class;

    /**
     * Zakładamy wątek przez serwis (tworzy wątek + pierwszą wiadomość +
     * powiadamia specjalistę). `body` jest non-dehydrated polem formularza.
     *
     * @param array<string,mixed> $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $tenant = app(TenantManager::class)->current();
        $user = Auth::user();

        if ($tenant === null || $user === null) {
            throw ValidationException::withMessages([
                'specialist_id' => __('app/specialist_thread.error.no_context'),
            ]);
        }

        $specialist = ExternalSpecialist::query()->findOrFail($data['specialist_id']);

        return app(SpecialistMessagingService::class)->startThread(
            tenant: $tenant,
            specialist: $specialist,
            subject: (string) $data['subject'],
            senderType: SpecialistMessage::SENDER_TENANT_USER,
            senderId: (string) $user->id,
            body: (string) ($this->data['body'] ?? ''),
            horseId: $data['horse_id'] ?? null,
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
