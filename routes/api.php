<?php

declare(strict_types=1);

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
