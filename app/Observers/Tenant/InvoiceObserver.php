<?php

declare(strict_types=1);

namespace App\Observers\Tenant;

use App\Domain\Notifications\Owner\OwnerNotificationDispatcher;
use App\Enums\InvoiceStatus;
use App\Models\Tenant\Invoice;
use App\Notifications\Owner\NewInvoiceForOwner;
use App\Tenancy\TenantManager;

/**
 * Faza 6 PR 6.1 — dispatch NewInvoiceForOwner gdy faktura przejdzie z
 * status=Draft do status=Issued (lub od razu utworzona jako Issued
 * przez stable side). Owner dostaje database + mail notification z
 * linkiem do owner panel'u InvoiceShow.
 *
 * Trigger via `saving` hook (przed save) żeby porównać oryginalny i
 * nowy status. Dispatch w `saved` (po commit), żeby invoice ID był
 * pewny.
 */
class InvoiceObserver
{
    /** @var array<string, bool> */
    private array $pendingDispatch = [];

    public function saving(Invoice $invoice): void
    {
        // Wyznaczamy "transition do issued" przez porównanie original vs
        // nowy status. Pierwsze save (nowy invoice) z status=issued też
        // łapiemy — getOriginal('status') zwróci null dla nowych modeli.
        $originalStatus = $invoice->getOriginal('status');
        $newStatus = $invoice->getAttribute('status');

        // Cast aware — status może być enum lub string w zależności od
        // stanu hydraty.
        $originalValue = $originalStatus instanceof InvoiceStatus
            ? $originalStatus->value
            : ($originalStatus !== null ? (string) $originalStatus : null);
        $newValue = $newStatus instanceof InvoiceStatus
            ? $newStatus->value
            : (string) $newStatus;

        $isTransitionToIssued = $newValue === InvoiceStatus::Issued->value
            && $originalValue !== InvoiceStatus::Issued->value;

        if ($isTransitionToIssued) {
            // Markujemy dla saved() — ID może się jeszcze nie zmaterializować
            // dla brand-new modelu w tym hook'u.
            $this->pendingDispatch[(string) $invoice->id] = true;
        }
    }

    public function saved(Invoice $invoice): void
    {
        $id = (string) $invoice->id;
        if (! isset($this->pendingDispatch[$id])) {
            return;
        }
        unset($this->pendingDispatch[$id]);

        $invoice->loadMissing('client');
        $client = $invoice->client;
        if ($client === null) {
            return;
        }

        $metadata = is_array($invoice->metadata) ? $invoice->metadata : [];
        $tenant = app(TenantManager::class)->current();
        $stableName = $tenant?->name ?? '';
        $stableId = $tenant !== null ? (string) $tenant->id : '';

        $ownerPanelUrl = $stableId !== ''
            ? url(sprintf('/owner/invoices/%s/%s', $stableId, $id))
            : url('/owner/invoices');

        app(OwnerNotificationDispatcher::class)->forClient($client, new NewInvoiceForOwner(
            stableTenantId: $stableId,
            stableName: (string) $stableName,
            invoiceId: $id,
            invoiceNumber: $invoice->number !== null ? (string) $invoice->number : null,
            totalCents: (int) $invoice->total_cents,
            currency: (string) $invoice->currency,
            dueAt: $invoice->due_at?->toDateString(),
            billingPeriod: isset($metadata['billing_period']) ? (string) $metadata['billing_period'] : null,
            horseName: isset($metadata['horse_name']) ? (string) $metadata['horse_name'] : null,
            ownerPanelUrl: $ownerPanelUrl,
        ));
    }
}
