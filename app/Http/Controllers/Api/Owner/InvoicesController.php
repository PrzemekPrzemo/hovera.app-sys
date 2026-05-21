<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Owner;

use App\Domain\Horses\HorseOwnerStableAccessGate;
use App\Domain\Invoicing\Owner\OwnerInvoiceFeedService;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Read-only API dla owner panel'u — lista i szczegóły faktur wystawionych
 * przez stajnie goszczące jego konie. Wszystko cross-tenant przez
 * OwnerInvoiceFeedService.
 *
 * Auth: Sanctum SPA mode (session cookie z `/owner` panel login) +
 * weryfikacja Client.central_user_id matching w cross-tenant context.
 * Throttle 60 req/min/user.
 *
 * Endpointy:
 *   GET /api/owner/invoices                                  globalna lista
 *   GET /api/owner/horses/{centralHorseId}/invoices          per koń
 *   GET /api/owner/invoices/{stableTenantId}/{invoiceId}     szczegóły
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 3 PR 3.3".
 */
class InvoicesController extends Controller
{
    public function __construct(
        private readonly OwnerInvoiceFeedService $feed,
        private readonly HorseOwnerStableAccessGate $horseGate,
    ) {}

    /**
     * Globalna lista wszystkich issued+ faktur ownera (across all stable'ów).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $invoices = $this->feed->forOwner($user);

        return response()->json([
            'data' => $invoices->map->toArray()->values()->all(),
            'count' => $invoices->count(),
        ]);
    }

    /**
     * Faktury filtrowane do jednego konia. Wymaga ownership'u (gate).
     * Per Q3 z roadmap: ended boarding daje dostęp do historycznych
     * faktur, więc nie gate'ujemy strict-active.
     */
    public function indexForHorse(Request $request, string $centralHorseId): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Ownership check: hasAnyActiveBoarding to za mało — owner może
        // mieć tylko ended assignment'y dla tego konia, ale chcemy żeby
        // widział faktury historyczne. Sprawdzamy primary_owner w
        // CentralHorseRegistry (gate.tryAuthorize sprawdza tylko active,
        // ale my potrzebujemy szerszego check'a).
        if (! $this->ownerOwnsHorse($user, $centralHorseId)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $invoices = $this->feed->forHorse($user, $centralHorseId);

        return response()->json([
            'data' => $invoices->map->toArray()->values()->all(),
            'count' => $invoices->count(),
        ]);
    }

    /**
     * Szczegóły pojedynczej faktury — composite (stableTenantId, invoiceId).
     * Gate inline: feed service zwróci null jeśli owner nie ma client
     * matching central_user_id w danej stajni.
     */
    public function show(Request $request, string $stableTenantId, string $invoiceId): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $invoice = $this->feed->findInvoice($user, $stableTenantId, $invoiceId);

        if ($invoice === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json(['data' => $invoice->toArray()]);
    }

    /**
     * Placeholder dla PDF download — pełna implementacja w przyszłej
     * iteracji (wymaga signed S3 URL + cross-tenant Storage::disk).
     */
    public function pdf(Request $request, string $stableTenantId, string $invoiceId): JsonResponse
    {
        return response()->json([
            'error' => __('owner/invoices.api.pdf_not_implemented'),
        ], 501);
    }

    /**
     * Placeholder dla online pay — pełna implementacja w przyszłej iteracji
     * (wymaga StableInvoicePaymentService — analogiczny do
     * TransporterP24QuoteService z reuse pattern).
     */
    public function pay(Request $request, string $stableTenantId, string $invoiceId): JsonResponse
    {
        return response()->json([
            'error' => __('owner/invoices.api.pay_not_implemented'),
        ], 501);
    }

    /**
     * Sprawdza czy user jest primary_owner w CentralHorseRegistry dla
     * tego konia. Szersze niż gate.authorize() (które wymaga ACTIVE
     * boarding'u) — pozwala dostęp do historycznych faktur po ended
     * boarding'u (per roadmap Q3).
     */
    private function ownerOwnsHorse(User $owner, string $centralHorseId): bool
    {
        return CentralHorseRegistry::query()
            ->where('id', $centralHorseId)
            ->where('primary_owner_user_id', $owner->id)
            ->exists();
    }
}
