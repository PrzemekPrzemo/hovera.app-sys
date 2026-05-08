<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Invitations\AcceptInvitationController;
use App\Http\Controllers\Public\BookingCancellationController;
use App\Http\Controllers\Public\ClientPortalController;
use App\Http\Controllers\Public\PaymentWebhookController;
use App\Http\Controllers\Public\PublicBookingController;
use App\Http\Controllers\Public\PublicInvoiceController;
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
 * unauthenticated users to a route named `login`. Master admin panel
 * doesn't register own login (świadomie) — wszyscy logują się przez
 * /app/login, Filament przy auth check sprawdza canAccessPanel.
 *
 * `/admin/login` to redirect dla starych bookmark'ów / pomyłek user'ów —
 * normalni właściciele stajni często trafiają na /admin/login zamiast
 * /app/login i nie mogą się zalogować bo nie są master adminami.
 */
Route::get('/login', fn () => redirect('/app/login'))->name('login');
Route::get('/'.config('hovera.admin.path', 'admin').'/login', fn () => redirect('/app/login'));

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
$publicPrefix = config('hovera.public_site.prefix', 's');
$slugRegex = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';

Route::middleware('web')
    ->get('/'.$publicPrefix.'/{slug}', [PublicSiteController::class, 'show'])
    ->where('slug', $slugRegex)
    ->name('public.tenant');

/*
 * Embed widgety — pojedyncze sekcje renderowane bez chrome (header/footer)
 * tak żeby stajnia mogła je wstawić jako <iframe> na swojej stronie WWW.
 *   /s/{slug}/embed/box-availability  → "X wolnych boksów" pill
 *   /s/{slug}/embed/booking            → mini-formularz rezerwacji (link do pełnego flow)
 *   /s/{slug}/embed/instructors        → lista instruktorów
 *   /s/{slug}/embed/pricing            → cennik
 */
Route::middleware('web')
    ->prefix('/'.$publicPrefix.'/{slug}/embed')
    ->where(['slug' => $slugRegex])
    ->name('public.embed.')
    ->group(function () {
        Route::get('/{widget}', [PublicSiteController::class, 'embed'])
            ->where(['widget' => 'box-availability|booking|instructors|pricing'])
            ->name('show');
    });

/*
 * Public booking flow — pick instructor / pick slot / contact form / submit.
 * No auth, lightly throttled to slow brute-force scanning.
 */
Route::middleware(['web', 'throttle:30,1'])
    ->prefix('/'.$publicPrefix.'/{slug}/book')
    ->where(['slug' => $slugRegex])
    ->name('public.booking.')
    ->group(function () {
        Route::get('/', [PublicBookingController::class, 'chooseInstructor'])->name('instructors');
        Route::get('/{instructor}', [PublicBookingController::class, 'pickSlot'])->name('slots');
        Route::get('/{instructor}/confirm', [PublicBookingController::class, 'confirmForm'])->name('confirm');
        Route::post('/{instructor}', [PublicBookingController::class, 'submit'])->name('submit');

        // Customer-facing cancel link (signed URL with TTL = booking start time)
        Route::get('/cancel/{entry}', [BookingCancellationController::class, 'show'])->name('cancel.show');
        Route::post('/cancel/{entry}', [BookingCancellationController::class, 'submit'])->name('cancel.submit');
    });

/*
 * Client portal — magic-link auth + dashboard. Rider enters email →
 * gets a one-shot signed URL → lands on a list of upcoming + past
 * bookings. Heavily throttled because the login endpoint sends mail.
 */
Route::middleware(['web', 'throttle:30,1'])
    ->prefix('/'.$publicPrefix.'/{slug}/portal')
    ->where(['slug' => $slugRegex])
    ->name('client_portal.')
    ->group(function () {
        Route::get('/login', [ClientPortalController::class, 'showLogin'])->name('login.show');
        Route::post('/login', [ClientPortalController::class, 'submitLogin'])
            ->middleware('throttle:6,1')
            ->name('login.submit');
        Route::get('/login/{client}/consume', [ClientPortalController::class, 'consumeLogin'])->name('login.consume');
        Route::post('/logout', [ClientPortalController::class, 'logout'])->name('logout');
        Route::get('/', [ClientPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/bookings/{entry}/reschedule', [ClientPortalController::class, 'showReschedule'])
            ->name('reschedule.show');
        Route::post('/bookings/{entry}/reschedule', [ClientPortalController::class, 'submitReschedule'])
            ->name('reschedule.submit');
        Route::get('/horses/{horse}', [ClientPortalController::class, 'showHorse'])->name('horses.show');
        Route::post('/horses/{horse}/messages', [ClientPortalController::class, 'sendHorseMessage'])->name('horses.messages.send');
        Route::get('/horses/{horse}/messages/{message}/attachment/{index}', [ClientPortalController::class, 'downloadAttachment'])
            ->name('horses.messages.attachment');
        Route::post('/horses/{horse}/documents', [ClientPortalController::class, 'uploadHorseDocument'])->name('horses.documents.upload');
        Route::get('/horses/{horse}/documents/{document}', [ClientPortalController::class, 'downloadHorseDocument'])->name('horses.documents.download');
        Route::delete('/horses/{horse}/documents/{document}', [ClientPortalController::class, 'deleteHorseDocument'])->name('horses.documents.delete');
        Route::get('/messages', [ClientPortalController::class, 'showMessages'])->name('messages.show');
    });

/*
 * Publiczny widok faktury z signed URL. Klient dostaje link mailem,
 * widzi fakturę + przycisk "Zapłać teraz" (jeśli stajnia ma payment
 * providera).
 */
Route::middleware(['web', 'throttle:30,1'])
    ->prefix('/'.$publicPrefix.'/{slug}/invoices')
    ->where(['slug' => $slugRegex])
    ->name('public.invoice.')
    ->group(function () {
        Route::get('/{invoice}', [PublicInvoiceController::class, 'show'])->name('show');
        Route::get('/{invoice}/pay', [PublicInvoiceController::class, 'pay'])->name('pay');
    });

/*
 * Tenant payment provider callbacks. The provider id is in the URL
 * segment; PaymentWebhookController routes it to the right service.
 *
 * Webhooks are unsigned at the route level (provider implementation
 * verifies signatures internally) and CSRF is bypassed via VerifyCsrfToken
 * exclusion (configured in app/Http/Middleware) — see provider docs.
 */
Route::middleware('web')
    ->prefix('/'.$publicPrefix.'/{slug}/payments')
    ->where(['slug' => $slugRegex, 'provider' => '[a-z0-9]+'])
    ->name('public.payments.')
    ->group(function () {
        Route::match(['get', 'post'], '/{provider}/webhook', [PaymentWebhookController::class, 'webhook'])
            ->name('webhook');
        Route::get('/{provider}/return/{payment}', [PaymentWebhookController::class, 'return'])
            ->name('return');
    });
