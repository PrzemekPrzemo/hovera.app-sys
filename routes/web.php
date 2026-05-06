<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Invitations\AcceptInvitationController;
use App\Http\Controllers\Public\PublicSiteController;
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

Route::middleware(['web', 'auth'])->prefix('impersonation')->name('impersonation.')->group(function () {
    Route::post('/stop', [ImpersonationController::class, 'stop'])->name('stop');
});

/*
 * Public invitation acceptance — the token IS the credential, no auth
 * middleware. Intentionally rate-limited to slow down brute force on
 * the 30-byte token space.
 */
Route::middleware(['web', 'throttle:6,1'])->prefix('invite')->name('invitations.')->group(function () {
    Route::get('/{token}', [AcceptInvitationController::class, 'show'])->name('accept');
    Route::post('/{token}', [AcceptInvitationController::class, 'submit'])->name('submit');
});

/*
 * Public per-stable micro-site — fully public, only needs `web` for
 * cookies / sessions if the page ever needs them. Cached at controller
 * level for 5 minutes.
 */
Route::middleware('web')
    ->get('/'.config('hovera.public_site.prefix', 's').'/{slug}', [PublicSiteController::class, 'show'])
    ->where('slug', '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?')
    ->name('public.tenant');
