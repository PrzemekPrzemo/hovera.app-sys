<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Enums\BoardingFrequency;
use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\BoardingService;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bulk-generate one Draft invoice per client for a given month, based on
 * each client's horses' active boarding services. Daily charges multiply
 * by month length; monthly are billed once.
 *
 * Output is always Draft — owner reviews each invoice in /app/invoices
 * and clicks "Wystaw" individually. Bulk = preparation, not blind issue.
 */
class GenerateBulkBoardingInvoices
{
    public const FREQUENCY_TYPES = [
        BoardingFrequency::Daily->value,
        BoardingFrequency::Monthly->value,
    ];

    public function __construct(
        private readonly InvoiceSnapshotPreparer $snapshotPreparer,
    ) {}

    /**
     * @return array{
     *     client_id:string,
     *     client_name:string,
     *     items: array<int, array{name:string, quantity:float, unit:string, unit_price_cents:int, vat_rate:string, net_cents:int, vat_cents:int, total_cents:int}>,
     *     net_cents:int,
     *     total_cents:int,
     * }[]
     */
    public function preview(Tenant $tenant, Carbon $monthStart): array
    {
        $monthStart = $monthStart->copy()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $daysInMonth = $monthStart->daysInMonth;

        $clients = Client::query()->orderBy('name')->get();
        $output = [];

        foreach ($clients as $client) {
            $items = $this->itemsForClient($client, $monthStart, $monthEnd, $daysInMonth);
            if (empty($items)) {
                continue;
            }

            $netSum = 0;
            $totalSum = 0;
            foreach ($items as $i) {
                $netSum += $i['net_cents'];
                $totalSum += $i['total_cents'];
            }

            $output[] = [
                'client_id' => (string) $client->id,
                'client_name' => (string) $client->name,
                'items' => $items,
                'net_cents' => $netSum,
                'total_cents' => $totalSum,
            ];
        }

        return $output;
    }

    /**
     * Persist one Draft invoice per selected client for the chosen month.
     * Returns the IDs of created invoices for the success notification.
     *
     * @param  list<string>  $clientIds  ids picked by the owner from the preview
     * @return list<string>
     */
    public function execute(Tenant $tenant, Carbon $monthStart, array $clientIds): array
    {
        $monthStart = $monthStart->copy()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $daysInMonth = $monthStart->daysInMonth;

        $clients = Client::query()->whereIn('id', $clientIds)->get();
        $createdIds = [];

        DB::connection('tenant')->transaction(function () use (
            $tenant, $clients, $monthStart, $monthEnd, $daysInMonth, &$createdIds
        ) {
            foreach ($clients as $client) {
                $items = $this->itemsForClient($client, $monthStart, $monthEnd, $daysInMonth);
                if (empty($items)) {
                    continue;
                }

                $createdIds[] = $this->persistDraft($tenant, $client, $items, $monthStart);
            }
        });

        return $createdIds;
    }

    /**
     * @return array<int, array{name:string, quantity:float, unit:string, unit_price_cents:int, vat_rate:string, net_cents:int, vat_cents:int, total_cents:int}>
     */
    private function itemsForClient(Client $client, Carbon $monthStart, Carbon $monthEnd, int $daysInMonth): array
    {
        $horses = Horse::query()
            ->where('owner_client_id', $client->id)
            ->with(['boardingServices' => fn ($q) => $q->whereIn('frequency', self::FREQUENCY_TYPES)
                ->where('is_active', true)])
            ->get();

        $items = [];

        foreach ($horses as $horse) {
            foreach ($horse->boardingServices as $service) {
                /** @var BoardingService $service */
                $pivot = $service->pivot;

                // Skip if explicit dates don't overlap the month.
                if ($pivot->starts_at && Carbon::parse($pivot->starts_at)->gt($monthEnd)) {
                    continue;
                }
                if ($pivot->ends_at && Carbon::parse($pivot->ends_at)->lt($monthStart)) {
                    continue;
                }

                $unitPriceCents = (int) ($pivot->price_override_cents ?? $service->price_cents);
                $quantity = (float) $pivot->quantity;

                if ($service->frequency === BoardingFrequency::Daily) {
                    $quantity *= $daysInMonth;
                }

                $netCents = (int) round($unitPriceCents * $quantity);

                // VAT calculation — vat_rate stored as string, may be "23" or "zw"/"np".
                $vatRate = (string) $service->vat_rate;
                $vatCents = is_numeric($vatRate)
                    ? (int) round($netCents * ((int) $vatRate / 100))
                    : 0;

                $items[] = [
                    'name' => $horse->name.' — '.$service->name,
                    'quantity' => $quantity,
                    'unit' => (string) ($service->unit ?? 'szt.'),
                    'unit_price_cents' => $unitPriceCents,
                    'vat_rate' => $vatRate,
                    'net_cents' => $netCents,
                    'vat_cents' => $vatCents,
                    'total_cents' => $netCents + $vatCents,
                ];
            }
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function persistDraft(Tenant $tenant, Client $client, array $items, Carbon $monthStart): string
    {
        $netSum = 0;
        $vatSum = 0;
        $totalSum = 0;
        foreach ($items as $i) {
            $netSum += $i['net_cents'];
            $vatSum += $i['vat_cents'];
            $totalSum += $i['total_cents'];
        }

        $issueDate = Carbon::now()->startOfDay();
        $dueDays = (int) (data_get($tenant->settings, 'invoicing.payment_due_days') ?? 14);
        $dueDate = $issueDate->copy()->addDays($dueDays);

        $invoice = Invoice::create([
            'id' => (string) Str::ulid(),
            'kind' => InvoiceKind::Fv->value,
            'status' => InvoiceStatus::Draft->value,
            'client_id' => $client->id,
            ...$this->snapshotPreparer->seller($tenant),
            ...$this->snapshotPreparer->buyer($client),
            'currency' => 'PLN',
            'sale_date' => $monthStart->copy()->endOfMonth()->toDateString(),
            'due_at' => $dueDate->toDateString(),
            'subtotal_cents' => $netSum,
            'vat_cents' => $vatSum,
            'total_cents' => $totalSum,
        ]);

        foreach ($items as $position => $item) {
            InvoiceItem::create([
                'id' => (string) Str::ulid(),
                'invoice_id' => $invoice->id,
                'position' => $position + 1,
                ...$item,
            ]);
        }

        return (string) $invoice->id;
    }
}
