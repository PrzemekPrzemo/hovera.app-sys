<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Tenant\TenantSelectorController;
use Illuminate\Support\Facades\Route;

/*
| Hovera routing
|
|   /                       Landing → redirects to /admin (marketing lives
|                           on hovera.app; this app is app.hovera.app).
|   /admin/*                Filament Master Admin panel.
|   /two-factor/*           TOTP enrolment + challenge.
|   /app/*                  Filament Tenant application panel.
|   /tenant/*               Tenant selection screens (post-login chooser).
|   /s/{slug}               Public per-stable micro-site (added later).
*/

Route::get('/', fn () => redirect('/'.config('hovera.admin.path')));

/*
 * Top-level `login` redirect: Laravel's `auth` middleware redirects
 * unauthenticated users to a route named `login`. Since each Filament
 * panel registers its own login (`/admin/login`, `/app/login`), the
 * top-level fallback sends them to the tenant panel by default.
 */
Route::get('/login', fn () => redirect('/app/login'))->name('login');

Route::middleware(['web', 'auth'])->prefix('two-factor')->name('two-factor.')->group(function () {
    Route::get('/setup', [TwoFactorController::class, 'showSetup'])->name('setup');
    Route::post('/setup', [TwoFactorController::class, 'confirmSetup'])->name('setup.confirm');
    Route::get('/recovery-codes', [TwoFactorController::class, 'showRecoveryCodes'])->name('recovery-codes');
    Route::get('/challenge', [TwoFactorController::class, 'showChallenge'])->name('challenge');
    Route::post('/challenge', [TwoFactorController::class, 'submitChallenge'])->name('challenge.submit');
});

Route::middleware(['web', 'auth'])->prefix('tenant')->name('tenant.')->group(function () {
    Route::get('/select', [TenantSelectorController::class, 'show'])->name('select');
    Route::post('/select', [TenantSelectorController::class, 'choose'])->name('select.choose');
    Route::get('/switch', [TenantSelectorController::class, 'switch'])->name('switch');
});
