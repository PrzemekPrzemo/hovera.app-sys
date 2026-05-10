<?php

declare(strict_types=1);

namespace App\Services\Exports;

use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\Invoice;
use App\Tenancy\TenantManager;
use RuntimeException;
use ZipArchive;

/**
 * Wyciąga snapshot danych tenant'a do ZIP (CSV-y + ICS + meta.json).
 * Przeznaczone dla master admina po expirze trial'u — żeby móc
 * wręczyć stajni dane do migracji do nowego tenant'a (rebrand /
 * podział firmy / refund po niewykorzystanym trialu).
 *
 * Użycie:
 *   $path = app(TenantDataExporter::class)->export($tenant);
 *   return response()->download($path)->deleteFileAfterSend();
 */
class TenantDataExporter
{
    public function __construct(private readonly TenantManager $tenants) {}

    /**
     * Zwraca pełną ścieżkę do ZIP'a w storage/app/exports/{slug}-{timestamp}.zip.
     * Caller odpowiada za usunięcie po wysłaniu.
     */
    public function export(Tenant $tenant): string
    {
        $dir = storage_path('app/exports');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create export directory: $dir");
        }

        $filename = sprintf('%s-%s.zip', $tenant->slug, now()->format('Ymd-His'));
        $path = $dir.DIRECTORY_SEPARATOR.$filename;

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot open ZIP for writing: $path");
        }

        // Przełącz tenant DB tylko jeśli aktualnie nie wskazuje na ten
        // tenant — w testach connection bywa już skonfigurowany na
        // sqlite, więc niepotrzebny reconfigure mysql by zabił testy.
        $current = $this->tenants->current();
        $alreadyCurrent = $current !== null && $current->id === $tenant->id;

        try {
            if (! $alreadyCurrent) {
                $this->tenants->execute($tenant, function () use ($zip, $tenant) {
                    $this->writeArchive($zip, $tenant);
                });
            } else {
                $this->writeArchive($zip, $tenant);
            }
        } finally {
            $zip->close();
        }

        return $path;
    }

    private function writeArchive(ZipArchive $zip, Tenant $tenant): void
    {
        $zip->addFromString('clients.csv', $this->clientsCsv());
        $zip->addFromString('horses.csv', $this->horsesCsv());
        $zip->addFromString('calendar.ics', $this->calendarIcs($tenant));
        $zip->addFromString('invoices.csv', $this->invoicesCsv());
        $zip->addFromString('meta.json', $this->metaJson($tenant));
    }

    private function clientsCsv(): string
    {
        return $this->buildCsv(
            ['id', 'name', 'email', 'phone', 'street', 'postal_code', 'city', 'country', 'tax_id', 'notes'],
            Client::query()->orderBy('name')->cursor()->map(fn (Client $c) => [
                $c->id, $c->name, $c->email, $c->phone,
                $c->street, $c->postal_code, $c->city, $c->country,
                $c->tax_id, $c->notes,
            ]),
        );
    }

    private function horsesCsv(): string
    {
        return $this->buildCsv(
            ['id', 'name', 'breed', 'sex', 'color', 'birth_date', 'microchip', 'passport_number', 'owner_email'],
            Horse::query()
                ->with('owner:id,email')
                ->orderBy('name')
                ->cursor()
                ->map(fn (Horse $h) => [
                    $h->id, $h->name, $h->breed, $h->sex, $h->color,
                    optional($h->birth_date)->format('Y-m-d'),
                    $h->microchip, $h->passport_number,
                    $h->owner?->email,
                ]),
        );
    }

    private function invoicesCsv(): string
    {
        return $this->buildCsv(
            ['number', 'kind', 'status', 'issued_at', 'due_at', 'paid_at', 'currency', 'total_cents', 'buyer_name', 'buyer_nip'],
            Invoice::query()->orderBy('issued_at')->cursor()->map(fn (Invoice $i) => [
                $i->number,
                $i->kind?->value,
                $i->status?->value,
                optional($i->issued_at)->format('Y-m-d'),
                optional($i->due_at)->format('Y-m-d'),
                optional($i->paid_at)?->format('Y-m-d H:i'),
                $i->currency,
                $i->total_cents,
                $i->buyer_name,
                $i->buyer_nip,
            ]),
        );
    }

    /**
     * iCalendar 2.0 — wszystkie calendar_entries jako VEVENT. Używamy
     * UTC w DTSTART/DTEND żeby uniknąć VTIMEZONE bloków (Apple
     * Calendar bywa picky o nie-IANA strefy). Klient może
     * zaimportować do Google/Apple jak normalny .ics export.
     */
    private function calendarIcs(Tenant $tenant): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//hovera//tenant-export//PL',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:'.$this->icsEscape($tenant->name),
        ];

        foreach (CalendarEntry::query()->orderBy('starts_at')->cursor() as $entry) {
            /** @var CalendarEntry $entry */
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$entry->id.'@hovera.app';
            $lines[] = 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:'.$entry->starts_at->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTEND:'.$entry->ends_at->utc()->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:'.$this->icsEscape((string) ($entry->title ?? $entry->type?->value ?? 'event'));
            if ($entry->notes !== null && $entry->notes !== '') {
                $lines[] = 'DESCRIPTION:'.$this->icsEscape((string) $entry->notes);
            }
            $lines[] = 'STATUS:'.strtoupper((string) ($entry->status?->value ?? 'CONFIRMED'));
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private function metaJson(Tenant $tenant): string
    {
        return (string) json_encode([
            'tenant_slug' => $tenant->slug,
            'tenant_name' => $tenant->name,
            'tenant_legal_name' => $tenant->legal_name,
            'tenant_tax_id' => $tenant->tax_id,
            'plan' => $tenant->plan?->code,
            'status' => $tenant->status,
            'trial_ends_at' => optional($tenant->trial_ends_at)->toIso8601String(),
            'created_at' => optional($tenant->created_at)->toIso8601String(),
            'exported_at' => now()->toIso8601String(),
            'export_format_version' => 1,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<int,string>  $headers
     * @param  iterable<int,array<int,mixed>>  $rows
     */
    private function buildCsv(array $headers, iterable $rows): string
    {
        $fh = fopen('php://temp', 'w+');
        if ($fh === false) {
            throw new RuntimeException('Cannot open temp stream for CSV');
        }
        // Excel-PL otwiera CSV poprawnie z UTF-8 BOM + przecinkiem.
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            fputcsv($fh, array_map(static fn ($v) => $v ?? '', $row));
        }
        rewind($fh);
        $data = stream_get_contents($fh);
        fclose($fh);

        return $data === false ? '' : $data;
    }

    private function icsEscape(string $s): string
    {
        return strtr($s, [
            "\r\n" => '\\n',
            "\n" => '\\n',
            ',' => '\\,',
            ';' => '\\;',
            '\\' => '\\\\',
        ]);
    }
}
