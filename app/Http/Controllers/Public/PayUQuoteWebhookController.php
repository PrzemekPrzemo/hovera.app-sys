<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Transport\Payments\PayU\TransporterPayUQuoteService;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Per-tenant PayU webhook dla quote payments. URL ma slug tenanta
 * w pathnie żeby router znał kontekst PRZED switch'em do tenant DB
 * (slug jest publiczny — `t/{slug}`).
 *
 * CSRF excluded w bootstrap/app.php (lista `transport/payu/webhook/*`).
 * Signature SHA256 z raw body + md5_key per tenant — weryfikowane
 * wewnątrz `TransporterPayUQuoteService::verifyWebhook`.
 *
 * URL: POST /transport/payu/webhook/{tenant_slug}
 *
 * Analogous do `Przelewy24QuoteWebhookController` — patrz docs/TRANSPORT.md §16.
 */
class PayUQuoteWebhookController extends Controller
{
    public function __construct(private readonly TransporterPayUQuoteService $payu) {}

    public function handle(Request $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();
        if ($tenant === null) {
            // 404 nieznany slug — PayU nie będzie retry (constant).
            return response()->json(['error' => 'unknown tenant'], 404);
        }

        // Switch do tenant DB żeby querować Quote.
        app(TenantManager::class)->use($tenant);

        $rawBody = (string) $request->getContent();
        $signature = (string) $request->header('OpenPayU-Signature', '');
        $payload = $request->json()->all();

        if ($rawBody === '' || $signature === '') {
            return response()->json(['error' => 'missing body or OpenPayU-Signature header'], 400);
        }

        try {
            $ok = $this->payu->processWebhook($tenant, $payload, $signature, $rawBody);
        } catch (\Throwable $e) {
            Log::error('PayU quote webhook unexpected error', [
                'tenant' => $tenantSlug,
                'error' => $e->getMessage(),
                'order_id' => $payload['order']['orderId'] ?? null,
            ]);

            return response()->json(['error' => 'internal error'], 500);
        }

        if (! $ok) {
            // signature mismatch / unknown quote / amount mismatch — 400
            // żeby PayU nie retry'ował (nie-transient).
            return response()->json(['error' => 'rejected'], 400);
        }

        return response()->json(['received' => true]);
    }
}
