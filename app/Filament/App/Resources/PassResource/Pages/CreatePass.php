<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\PassResource\Pages;

use App\Actions\Invoicing\CreateInvoiceFromPass;
use App\Filament\App\Resources\PassResource;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePass extends CreateRecord
{
    protected static string $resource = PassResource::class;

    protected function afterCreate(): void
    {
        $pass = $this->record;

        app(TenantAuditLogger::class)->record(
            'pass.create',
            'Pass',
            (string) $pass->getKey(),
            ['name' => $pass->name, 'total_uses' => $pass->total_uses],
        );

        // Auto-FV za karnet — domyślnie ON gdy karnet ma cenę.
        // Owner może wyłączyć przekazując metadata.skip_invoice = true
        // (np. gdy klient płaci za karnet poprzez online checkout —
        // wtedy faktura wystawi się po payment.succeeded zamiast tu).
        $skipInvoice = (bool) (data_get($pass->metadata, 'skip_invoice') ?? false);
        if (! $skipInvoice && $pass->price_cents) {
            $tenant = app(TenantManager::class)->current();
            if ($tenant) {
                $invoice = app(CreateInvoiceFromPass::class)->execute($tenant, $pass);
                if ($invoice && $invoice->number) {
                    Notification::make()
                        ->title('Wystawiono fakturę '.$invoice->number)
                        ->success()
                        ->send();
                }
            }
        }
    }
}
