<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Billing\Przelewy24Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Central P24 webhook dla add-on purchases (Hovera-as-merchant). CSRF
 * excluded w bootstrap/app.php. Reuses Przelewy24Service::verifyWebhook
 * dla sign check + Przelewy24Service::processAddonWebhook dla flip
 * statusu.
 *
 * URL: POST /webhooks/przelewy24/addon
 *
 * Differs od istniejącego /webhooks/przelewy24 (który obsługuje
 * Invoice → tenants → Hovera SaaS subskrypcje) tylko targetem —
 * sessionId tutaj to AddonPurchase.id, nie Invoice.id. Routing na
 * podstawie URL'a — czysta separacja.
 */
class Przelewy24AddonWebhookController extends Controller
{
    public function __construct(private readonly Przelewy24Service $p24) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (empty($payload) || empty($payload['sign'])) {
            return response()->json(['error' => 'missing payload or sign'], 400);
        }

        try {
            $ok = $this->p24->processAddonWebhook($payload);
        } catch (\Throwable $e) {
            Log::error('P24 addon webhook unexpected error', [
                'error' => $e->getMessage(),
                'session_id' => $payload['sessionId'] ?? null,
            ]);

            return response()->json(['error' => 'internal error'], 500);
        }

        if (! $ok) {
            return response()->json(['error' => 'rejected'], 400);
        }

        return response()->json(['received' => true]);
    }
}
