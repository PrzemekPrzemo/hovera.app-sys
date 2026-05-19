<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Billing\PayUService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Central PayU webhook dla invoices (Hovera-as-merchant — Hovera inkasuje
 * od tenantów za SaaS billing). Patrz docs/TRANSPORT.md §16.
 *
 * URL: POST /webhooks/payu
 * CSRF excluded w bootstrap/app.php.
 *
 * Signature verification: SHA256(raw_body + md5_key), header
 * `OpenPayU-Signature`. Wnętrze obsługuje `PayUService::processWebhook`.
 *
 * Idempotent — PayU może retransmitować ten sam webhook (np. po timeout),
 * service zwraca true bez side-effect dla już zapłaconych FV.
 */
class PayUWebhookController extends Controller
{
    public function __construct(private readonly PayUService $payu) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = (string) $request->getContent();
        $signature = (string) $request->header('OpenPayU-Signature', '');
        $payload = $request->json()->all();

        if ($rawBody === '' || $signature === '') {
            return response()->json(['error' => 'missing body or OpenPayU-Signature header'], 400);
        }

        try {
            $ok = $this->payu->processWebhook($payload, $signature, $rawBody);
        } catch (\Throwable $e) {
            Log::error('PayU webhook unexpected error', [
                'error' => $e->getMessage(),
                'order_id' => $payload['order']['orderId'] ?? null,
            ]);

            return response()->json(['error' => 'internal error'], 500);
        }

        if (! $ok) {
            return response()->json(['error' => 'rejected'], 400);
        }

        return response()->json(['received' => true]);
    }
}
