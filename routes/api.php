<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\UploadController;
use Illuminate\Support\Facades\Route;

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
