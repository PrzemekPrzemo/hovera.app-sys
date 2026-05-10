<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\WebhookSubscriptionResource\Pages;

use App\Filament\Admin\Resources\WebhookSubscriptionResource;
use App\Services\MasterAuditLogger;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWebhookSubscription extends EditRecord
{
    protected static string $resource = WebhookSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function () {
                    app(MasterAuditLogger::class)->record(
                        'webhook.deleted',
                        'WebhookSubscription',
                        (string) $this->record->id,
                        (string) $this->record->tenant_id,
                    );
                }),
        ];
    }

    protected function afterSave(): void
    {
        app(MasterAuditLogger::class)->record(
            'webhook.updated',
            'WebhookSubscription',
            (string) $this->record->id,
            (string) $this->record->tenant_id,
            [
                'url' => $this->record->url,
                'events' => $this->record->events,
                'is_active' => $this->record->is_active,
            ],
        );
    }
}
