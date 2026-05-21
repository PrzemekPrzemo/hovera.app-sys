<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Domain\Invoicing\Owner\OwnerInvoiceFeedService;
use App\Domain\Invoicing\Owner\Snapshots\InvoiceSummarySnapshot;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * C.7 z OWNER-STABLE-ROADMAP — owner eksportuje listę faktur jako CSV
 * filtrowaną do konkretnego roku (lub wszystkie).
 *
 * URL: GET /owner/invoices/export.csv?year=2026
 *
 * CSV columns (UTF-8 BOM dla Excela):
 *   number, stable, horse, billing_period, issued_at, due_at, paid_at,
 *   status, currency, total_cents, total_formatted
 *
 * Auth: Filament owner panel routes — `web` + `auth` middleware
 * (zarządzane przez OwnerPanelProvider). User musi być zalogowany;
 * service samostarczalnie filtruje per `Client.central_user_id`.
 */
class InvoiceCsvExportController extends Controller
{
    public function __construct(
        private readonly OwnerInvoiceFeedService $service,
    ) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $yearRaw = $request->query('year');
        $year = is_numeric($yearRaw) && (int) $yearRaw >= 2020 && (int) $yearRaw <= 2100
            ? (int) $yearRaw
            : null;

        $invoices = $year !== null
            ? $this->service->forOwnerYear($user, $year)
            : $this->service->forOwner($user);

        $filename = $year !== null
            ? sprintf('hovera-invoices-%d.csv', $year)
            : 'hovera-invoices-all.csv';

        return response()->streamDownload(function () use ($invoices) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM — Excel respektuje przy otwarciu CSV (bez tego
            // ą/ć/ż lecą jako mojibake).
            fwrite($out, "\xEF\xBB\xBF");

            // Header.
            fputcsv($out, [
                'number',
                'stable',
                'horse',
                'billing_period',
                'issued_at',
                'due_at',
                'paid_at',
                'status',
                'currency',
                'total_cents',
                'total_formatted',
            ], ',', '"', '\\');

            /** @var InvoiceSummarySnapshot $inv */
            foreach ($invoices as $inv) {
                fputcsv($out, [
                    $inv->number ?? '',
                    $inv->stableTenantName,
                    $inv->horseName ?? '',
                    $inv->billingPeriod ?? '',
                    $inv->issuedAt?->format('Y-m-d') ?? '',
                    $inv->dueAt?->format('Y-m-d') ?? '',
                    $inv->paidAt?->format('Y-m-d') ?? '',
                    $inv->status,
                    $inv->currency,
                    $inv->totalCents,
                    number_format($inv->totalCents / 100, 2, ',', ' ').' '.$inv->currency,
                ], ',', '"', '\\');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }
}
