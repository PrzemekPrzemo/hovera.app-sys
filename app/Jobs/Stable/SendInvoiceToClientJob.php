<?php

declare(strict_types=1);

namespace App\Jobs\Stable;

use App\Enums\InvoiceStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice;
use App\Notifications\InvoiceIssuedClientNotification;
use App\Services\Invoicing\InvoicePublicLink;
use App\Services\Portal\ClientMessageJournal;
use App\Tenancy\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as MailFacade;

/**
 * Async wysyłka FV mailem do klienta — mirror logiki z single-row
 * Filament `email` action (InvoiceResource), ale w job queue, żeby
 * bulk wysyłka 100+ FV nie blokowała HTTP request'a.
 *
 * Idempotencja: skipuje FV z `email_sent_at` set (chyba że `force=true`
 * — bulk action "Wyślij ponownie" po stronie operatora).
 *
 * Reuse `InvoiceIssuedClientNotification` + `InvoicePublicLink` +
 * `ClientMessageJournal` — żeby pojedynczy mail i bulk dawały
 * IDENTYCZNY efekt z perspektywy klienta i portalu.
 */
class SendInvoiceToClientJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $invoiceId,
        public readonly bool $force = false,
    ) {}

    public function handle(TenantManager $tenants): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            Log::warning('SendInvoiceToClient: tenant not found', ['tenant_id' => $this->tenantId]);

            return;
        }

        // Skip-if-same-tenant — gdy job leci w już aktywnym kontekście
        // (testy, queue worker który zachował tenancy), nie nadpisujemy
        // configa connection'a (mirror SnapshotTenantHealthCommand pattern).
        $callback = function () use ($tenant): void {
            $invoice = Invoice::with('client')->find($this->invoiceId);
            if ($invoice === null) {
                return;
            }

            if (! $this->force && $invoice->email_sent_at !== null) {
                return;
            }
            if (! $invoice->status->isPosted()) {
                return; // tylko issued/paid wysyłamy do klienta
            }

            $email = $invoice->client?->email;
            if (! is_string($email) || $email === '') {
                Log::info('SendInvoiceToClient: no email', ['invoice_id' => $invoice->id]);

                return;
            }

            try {
                $url = app(InvoicePublicLink::class)->for($invoice, $tenant->slug);
                $canPay = ((string) (data_get($tenant->settings, 'payments.default_provider') ?? 'none')) !== 'none';

                MailFacade::route('mail', $email)->notify(new InvoiceIssuedClientNotification(
                    tenantName: $tenant->name,
                    invoiceNumber: (string) $invoice->number,
                    kindLabel: $invoice->kind->label(),
                    totalFormatted: $invoice->totalFormatted(),
                    issuedAt: $invoice->issued_at,
                    dueAt: $invoice->due_at,
                    publicUrl: $url,
                    canPayOnline: $canPay && $invoice->status === InvoiceStatus::Issued,
                ));

                if ($invoice->client) {
                    app(ClientMessageJournal::class)->record(
                        $invoice->client,
                        'invoice.issued',
                        $invoice->kind->label().' '.$invoice->number,
                        ['amount' => $invoice->totalFormatted()],
                        'Invoice',
                        (string) $invoice->id,
                    );
                }

                $invoice->forceFill(['email_sent_at' => now()])->save();
            } catch (\Throwable $e) {
                Log::warning('SendInvoiceToClient: send failed', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }
        };

        if ($tenants->current()?->id === $tenant->id) {
            $callback();
        } else {
            $tenants->execute($tenant, $callback);
        }
    }
}
