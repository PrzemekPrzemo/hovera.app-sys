<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Billing\PayUService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Central PayU webhook dla add-on purchases (Hovera-as-merchant). Patrz
 * docs/TRANSPORT.md §16.
 *
 * URL: POST /webhooks/payu/addon
 * CSRF excluded w bootstrap/app.php.
 *
 * Differs od /webhooks/payu (invoices) tylko targetem — orderId tu jest
 * AddonPurchase.payu_order_id, tam Invoice.payu_order_id. Routing na
 * podstawie URL'a — czysta separacja, brak ambiguity per orderId.
 */
class PayUAddonWebhookController extends Controller
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
            $ok = $this->payu->processAddonWebhook($payload, $signature, $rawBody);
        } catch (\Throwable $e) {
            Log::error('PayU addon webhook unexpected error', [
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
