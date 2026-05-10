<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\WebhookSubscriptionResource\Pages;

use App\Filament\Admin\Resources\WebhookSubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWebhookSubscriptions extends ListRecords
{
    protected static string $resource = WebhookSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
