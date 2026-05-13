<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\InvoiceResource;
use App\Models\Tenant\Invoice;
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

    public function pdf(string $id): StreamedResponse|JsonResponse
    {
        $invoice = Invoice::query()->findOrFail($id);

        $path = $invoice->pdf_path ?? $invoice->pdf_url;
        if (! $path) {
            return new JsonResponse(['error' => ['code' => 'pdf_unavailable']], 404);
        }

        // pdf_url may be a public CDN URL — just redirect.
        if (str_starts_with((string) $path, 'http')) {
            return new JsonResponse(['url' => $path]);
        }

        $disk = Storage::disk(config('filesystems.default'));
        if (! $disk->exists($path)) {
            return new JsonResponse(['error' => ['code' => 'pdf_missing']], 404);
        }

        return $disk->response($path, sprintf('invoice-%s.pdf', $invoice->number ?? $invoice->id));
    }
}
