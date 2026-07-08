<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\InvoiceResource;
use App\Models\Tenant\Invoice;
use App\Services\Invoicing\InvoicePdfStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController
{
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->orderByDesc('issued_at')
            ->limit((int) min($request->query('limit', 50), 200));

        if ($from = $request->query('from')) {
            $query->where('issued_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('issued_at', '<=', $to);
        }

        return new JsonResponse([
            'data' => InvoiceResource::collection($query->get())->resolve($request),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $invoice = Invoice::query()->findOrFail($id);

        return new JsonResponse((new InvoiceResource($invoice))->resolve(request()));
    }

    /**
     * Serves the hosted invoice PDF while it's within the local retention
     * window (issued year + 1 month grace — see `InvoicePdfStorageService`).
     * Past that window we no longer host the file; the response instead
     * points the client at KSeF, where every submitted invoice has a
     * permanent record.
     */
    public function pdf(string $id, InvoicePdfStorageService $pdfStorage): StreamedResponse|JsonResponse
    {
        $invoice = Invoice::query()->findOrFail($id);

        $available = false;
        try {
            $available = $pdfStorage->ensureStored($invoice);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($available) {
            return Storage::disk((string) $invoice->pdf_disk)
                ->response((string) $invoice->pdf_path, sprintf('invoice-%s.pdf', $invoice->number ?? $invoice->id));
        }

        return new JsonResponse([
            'error' => [
                'code' => 'pdf_no_longer_hosted',
                'message' => 'hovera.app no longer hosts this invoice PDF locally; redownload it from KSeF.',
            ],
            ...$pdfStorage->ksefRedirectPayload($invoice),
        ], 410);
    }
}
