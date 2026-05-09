<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public endpoint for Stripe webhook deliveries. CSRF is bypassed in
 * bootstrap/app.php (`webhooks/stripe`); the signature header is the
 * authentication mechanism.
 *
 * Always returns 200 on signature/idempotent dedupe — Stripe interprets
 * non-2xx as "retry". Hard errors return 500 so Stripe DOES retry.
 */
class StripeWebhookController extends Controller
{
    public function __construct(private readonly StripeBillingService $billing) {}

    public function __invoke(Request $request): JsonResponse
    {
        $signature = (string) $request->header('Stripe-Signature', '');
        $payload = (string) $request->getContent();

        if ($signature === '' || $payload === '') {
            return response()->json(['error' => 'missing payload or signature'], 400);
        }

        try {
            $this->billing->handleWebhook($payload, $signature);
        } catch (\RuntimeException $e) {
            // Signature mismatch — 400 is the correct response (Stripe
            // won't retry, and an attacker probing the endpoint gets
            // a clean rejection).
            if (str_contains($e->getMessage(), 'signature verification failed')) {
                return response()->json(['error' => 'invalid signature'], 400);
            }

            Log::error('Stripe webhook processing failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'processing failed'], 500);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook unexpected error', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'internal error'], 500);
        }

        return response()->json(['received' => true]);
    }
}
