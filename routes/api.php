<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Owner\InvoicesController as OwnerInvoicesController;
use App\Http\Controllers\Api\Owner\MessagesController as OwnerMessagesController;
use App\Http\Controllers\Api\PlacesAutocompleteController;
use App\Http\Controllers\Api\Transport\CalculatorPreviewController;
use App\Http\Controllers\Api\TransportInquiryApiController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Middleware\ResolveEmbedCors;
use Illuminate\Support\Facades\Route;

/*
 * Embed snippet API — `POST /api/transport/inquiry`. Transporter osadza
 * formularz zapytania na swojej stronie (HTML+JS snippet z
 * `/transport/embed-snippet`), JS posta tutaj. Patrz docs/TRANSPORT.md §16.
 *
 * Gating (defense-in-depth):
 *   - `ResolveEmbedCors` middleware: per-tenant whitelist `embed_allowed_origins`
 *     z `transport_settings`. Zły origin → brak CORS headers (browser block).
 *   - `X-Hovera-Embed-Token` header weryfikowany w controllerze (constant-time).
 *   - Honeypot `website` field — silent 200.
 *   - Throttle 10/h/IP — wyższy niż public `/transport/zapytanie` (5/h) bo
 *     embed może mieć więcej bona-fide traffic z popularnych stron.
 *
 * CSRF już auto-excluded dla `api/*` (bootstrap/app.php).
 */
Route::middleware([ResolveEmbedCors::class])
    ->prefix('transport')
    ->name('api.transport.')
    ->group(function () {
        Route::options('/inquiry', fn () => response()->noContent(204));
        Route::post('/inquiry', [TransportInquiryApiController::class, 'store'])
            ->middleware('throttle:10,60')
            ->name('inquiry');
    });

/*
 * Places autocomplete proxy — używany przez Filament Calculator/Quote
 * (kontext=panel) oraz publiczny `/transport/zapytanie` (kontext=public).
 * Tokeny Mapbox czytane server-side, NIE eksponowane do przeglądarki.
 *
 * Throttle 60 req/min/IP — typeahead w UI bije częściej niż raz na sekundę.
 */
Route::middleware('throttle:60,1')
    ->prefix('transport/places')
    ->name('api.transport.places.')
    ->group(function () {
        Route::get('/suggest', [PlacesAutocompleteController::class, 'suggest'])
            ->name('suggest');
    });

/*
 * Live preview kalkulatora wyceny — JS w Filament Calculator page bije
 * tutaj z debounce 500ms po każdej zmianie pola, żeby sticky summary
 * card mogła pokazywać aktualną cenę bez submit'u formy. Patrz
 * docs/MARKETPLACE-ROADMAP.md "Calculator live UX".
 *
 * Auth: Sanctum SPA mode (session cookie z `/app` panel login).
 * EnsureFrontendRequestsAreStateful (middleware api) wstrzykuje web
 * middleware dla stateful requestów — HydrateTenantConnectionFromSession
 * ustawi tenant DB z `current_tenant_id` w session.
 *
 * Throttle: 60 req/min/user — debounce 500ms po stronie JS realnie
 * trzyma to w okolicach 5–10/min/user.
 */
Route::middleware(['auth:sanctum', 'throttle:60,1'])
    ->prefix('transport/calculator')
    ->name('api.transport.calculator.')
    ->group(function () {
        Route::post('/preview', CalculatorPreviewController::class)
            ->name('preview');
    });

/*
 * Owner panel API — cross-tenant read access do faktur wystawionych
 * przez stajnie goszczące konie ownera. Wszystkie endpointy wymagają
 * Sanctum SPA session (cookie z `/owner` panel login) + ownership
 * weryfikacji w controller'ach (gate przez CentralHorseRegistry +
 * Client.central_user_id matching).
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 3 PR 3.3".
 */
Route::middleware(['auth:sanctum', 'throttle:60,1'])
    ->prefix('owner')
    ->name('api.owner.')
    ->group(function () {
        // Globalna lista wszystkich faktur ownera
        Route::get('/invoices', [OwnerInvoicesController::class, 'index'])
            ->name('invoices.index');
        // Per-koń lista
        Route::get('/horses/{centralHorseId}/invoices', [OwnerInvoicesController::class, 'indexForHorse'])
            ->name('horses.invoices');
        // Szczegóły pojedynczej faktury — composite (stableTenantId, invoiceId)
        Route::get('/invoices/{stableTenantId}/{invoiceId}', [OwnerInvoicesController::class, 'show'])
            ->name('invoices.show');
        // Placeholdery — pełna implementacja w przyszłej iteracji
        Route::get('/invoices/{stableTenantId}/{invoiceId}/pdf', [OwnerInvoicesController::class, 'pdf'])
            ->name('invoices.pdf');
        Route::post('/invoices/{stableTenantId}/{invoiceId}/pay', [OwnerInvoicesController::class, 'pay'])
            ->name('invoices.pay');

        // Wiadomości Owner ↔ Stable (Faza 4). Threading per koń + read
        // receipts. Attachments JSON na razie placeholder (storage layer
        // przyjdzie w PR 4.2). Patrz docs/OWNER-STABLE-ROADMAP.md.
        Route::get('/horses/{centralHorseId}/messages', [OwnerMessagesController::class, 'indexForHorse'])
            ->name('horses.messages.index');
        Route::post('/horses/{centralHorseId}/messages', [OwnerMessagesController::class, 'send'])
            ->name('horses.messages.send');
        Route::post('/messages/{stableTenantId}/{messageId}/read', [OwnerMessagesController::class, 'markRead'])
            ->name('messages.read');
        Route::get('/messages/unread-count', [OwnerMessagesController::class, 'unreadCount'])
            ->name('messages.unread_count');
    });

// Public auth endpoints (no tenant context yet — user picks tenant after login).
Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
});

// Authenticated, tenant-scoped endpoints.
Route::prefix('v1')
    ->middleware(['api.tenant', 'api.throttle:120'])
    ->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::post('devices', [DeviceController::class, 'store']);
        Route::delete('devices/{token}', [DeviceController::class, 'destroy']);

        // Sync surface — stricter rate limit (heavier endpoints).
        Route::middleware('api.throttle:30')->group(function () {
            Route::get('sync/changes', [SyncController::class, 'changes']);
            Route::post('sync/mutations', [SyncController::class, 'mutations']);
            Route::post('uploads/horse-photos', [UploadController::class, 'horsePhoto']);
            Route::post('uploads/horse-documents', [UploadController::class, 'horseDocument']);
        });

        // Read-side resources that mobile clients can request directly
        // (in addition to the change feed) for screens that need fresh state.
        Route::get('invoices', [InvoiceController::class, 'index']);
        Route::get('invoices/{id}', [InvoiceController::class, 'show']);
        Route::get('invoices/{id}/pdf', [InvoiceController::class, 'pdf']);
    });
