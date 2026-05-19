<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Transport\Payments\Przelewy24\TransporterP24QuoteService;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Per-tenant P24 webhook dla quote payments. URL ma slug tenanta w
 * pathnie żeby router znał kontekst PRZED switch'em do tenant DB.
 *
 * CSRF excluded w bootstrap/app.php (lista `transport/p24/webhook/*`).
 * Signature verification (SHA384) wewnątrz TransporterP24QuoteService
 * używając crc_key z `tenants.settings.payments.p24`.
 *
 * URL: POST /transport/p24/webhook/{tenant_slug}
 *
 * UWAGA: To jest OPCJONALNY webhook. Transporter może też skonfigurować
 * własny URL bezpośrednio w panelu Przelewy24 (np. swoja własna app
 * księgowa) — wtedy Hovera nigdy nie dostanie notification i fall-back
 * to manual "Oznacz jako opłacone" w `/transport/quotes`. Patrz
 * docs/TRANSPORT.md §15.5.
 */
class Przelewy24QuoteWebhookController extends Controller
{
    public function __construct(private readonly TransporterP24QuoteService $p24) {}

    public function handle(Request $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();
        if ($tenant === null) {
            // 404 nieznany slug — P24 nie będzie retry (constant).
            return response()->json(['error' => 'unknown tenant'], 404);
        }

        // Switch do tenant DB żeby querować Quote
        app(TenantManager::class)->use($tenant);

        $payload = $request->all();

        if (empty($payload) || empty($payload['sign'])) {
            return response()->json(['error' => 'missing payload or sign'], 400);
        }

        try {
            $ok = $this->p24->processWebhook($tenant, $payload);
        } catch (\Throwable $e) {
            Log::error('P24 quote webhook unexpected error', [
                'tenant' => $tenantSlug,
                'error' => $e->getMessage(),
                'session_id' => $payload['sessionId'] ?? null,
            ]);

            return response()->json(['error' => 'internal error'], 500);
        }

        if (! $ok) {
            // sign mismatch / unknown quote / verify failed — 400
            // żeby P24 nie retry'ował (te przypadki nie są transient).
            return response()->json(['error' => 'rejected'], 400);
        }

        return response()->json(['received' => true]);
    }
}
