<?php

use App\Http\Controllers\Auth\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
| Hovera routing
|
|   /                       Landing → redirects to login (marketing lives on
|                           hovera.app, this app is app.hovera.app).
|   /admin/*                Filament Master Admin panel (separate provider).
|   /two-factor/*           TOTP enrolment + challenge.
|   /app/*                  Tenant application (added in next iteration).
|   /s/{slug}               Public per-stable micro-site (added later).
*/

Route::get('/', fn () => redirect('/' . config('hovera.admin.path')));

Route::middleware('web')->group(function () {
    Route::middleware('auth')->prefix('two-factor')->name('two-factor.')->group(function () {
        Route::get('/setup', [TwoFactorController::class, 'showSetup'])->name('setup');
        Route::post('/setup', [TwoFactorController::class, 'confirmSetup'])->name('setup.confirm');
        Route::get('/recovery-codes', [TwoFactorController::class, 'showRecoveryCodes'])->name('recovery-codes');
        Route::get('/challenge', [TwoFactorController::class, 'showChallenge'])->name('challenge');
        Route::post('/challenge', [TwoFactorController::class, 'submitChallenge'])->name('challenge.submit');
    });
});
