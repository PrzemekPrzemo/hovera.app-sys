<?php

declare(strict_types=1);

/*
| Hovera API routes (mobile / 3rd party)
|
| Foundation (Sanctum, sync models, push channels, middleware,
| docs/API.md) jest gotowa od PR #151 + #152, ale konkretne controllery
| (Api\V1\AuthController, SyncController, DevicesController, UploadsController)
| dopiero przed nami.
|
| Bootstrap (bootstrap/app.php) referencuje ten plik:
|     api: __DIR__.'/../routes/api.php'
| więc nie może go nie być — bez stuba `php artisan optimize` wywala
| "Failed to open stream: routes/api.php".
|
| Endpointy do dodania (patrz docs/API.md):
|   POST   /api/v1/auth/login
|   POST   /api/v1/auth/refresh
|   POST   /api/v1/auth/logout
|   GET    /api/v1/sync/changes
|   POST   /api/v1/sync/mutations
|   POST   /api/v1/uploads/horse-photos
|   POST   /api/v1/devices
|   DELETE /api/v1/devices/{token}
*/

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'hovera-api',
    'version' => 'v1',
]));
