<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\WebhookSubscriptionResource\Pages;

use App\Filament\Admin\Resources\WebhookSubscriptionResource;
use App\Services\MasterAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateWebhookSubscription extends CreateRecord
{
    protected static string $resource = WebhookSubscriptionResource::class;

    protected function afterCreate(): void
    {
        app(MasterAuditLogger::class)->record(
            'webhook.created',
            'WebhookSubscription',
            (string) $this->record->id,
            (string) $this->record->tenant_id,
            [
                'url' => $this->record->url,
                'events' => $this->record->events,
            ],
        );
    }
}
