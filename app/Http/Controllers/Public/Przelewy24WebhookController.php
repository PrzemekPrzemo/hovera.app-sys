<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Billing\Przelewy24Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public endpoint for Przelewy24 notification deliveries (CENTRAL —
 * SaaS hovera invoices). CSRF bypass + signature verification inside
 * Przelewy24Service::verifyWebhook.
 *
 * P24 notification jest application/json POST z polami:
 *   merchantId, posId, sessionId, amount, originAmount, currency,
 *   orderId, methodId, statement, sign
 *
 * Zwracamy 200 nawet przy idempotent dedupe — P24 traktuje non-2xx
 * jako "retry, retry, retry" przez 7 dni. Hard errors → 500 (chcemy
 * retry). Bad sign → 400 (P24 zatrzymuje się).
 */
class Przelewy24WebhookController extends Controller
{
    public function __construct(private readonly Przelewy24Service $p24) {}

    public function __invoke(Request $request): JsonResponse
    {
        // P24 wysyła application/json (nowe API v1) lub form-encoded
        // (legacy) — Request::all() obsługuje oba.
        $payload = $request->all();

        if (empty($payload) || empty($payload['sign'])) {
            return response()->json(['error' => 'missing payload or sign'], 400);
        }

        try {
            $ok = $this->p24->processWebhook($payload);
        } catch (\Throwable $e) {
            Log::error('P24 webhook unexpected error', [
                'error' => $e->getMessage(),
                'session_id' => $payload['sessionId'] ?? null,
            ]);

            return response()->json(['error' => 'internal error'], 500);
        }

        if (! $ok) {
            // sign mismatch / unknown invoice / verify failed — 400
            // żeby P24 nie retry'ował (te przypadki nie są transient).
            return response()->json(['error' => 'rejected'], 400);
        }

        return response()->json(['received' => true]);
    }
}
