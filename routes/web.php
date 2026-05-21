<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Invitations\AcceptInvitationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MasterAdController;
use App\Http\Controllers\Owner\InvoiceCsvExportController;
use App\Http\Controllers\Public\BookingCancellationController;
use App\Http\Controllers\Public\ClientPortalController;
use App\Http\Controllers\Public\DemoLoginController;
use App\Http\Controllers\Public\HelpController;
use App\Http\Controllers\Public\HorseOwnerRegistrationController;
use App\Http\Controllers\Public\InstructorCalendarController;
use App\Http\Controllers\Public\LegalController;
use App\Http\Controllers\Public\PaymentWebhookController;
use App\Http\Controllers\Public\PayUAddonReturnController;
use App\Http\Controllers\Public\PayUAddonWebhookController;
use App\Http\Controllers\Public\PayUQuoteReturnController;
use App\Http\Controllers\Public\PayUQuoteWebhookController;
use App\Http\Controllers\Public\PayUWebhookController;
use App\Http\Controllers\Public\PricingController;
use App\Http\Controllers\Public\Przelewy24AddonReturnController;
use App\Http\Controllers\Public\Przelewy24AddonWebhookController;
use App\Http\Controllers\Public\Przelewy24Controller;
use App\Http\Controllers\Public\Przelewy24QuoteReturnController;
use App\Http\Controllers\Public\Przelewy24QuoteWebhookController;
use App\Http\Controllers\Public\Przelewy24WebhookController;
use App\Http\Controllers\Public\PublicBookingController;
use App\Http\Controllers\Public\PublicInvoiceController;
use App\Http\Controllers\Public\PublicSiteController;
use App\Http\Controllers\Public\QuoteAcceptanceController;
use App\Http\Controllers\Public\SignupController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\StripeConnectWebhookController;
use App\Http\Controllers\Public\StripeWebhookController;
use App\Http\Controllers\Public\TransporterDirectoryController;
use App\Http\Controllers\Public\TransporterOgImageController;
use App\Http\Controllers\Public\TransporterOnboardingController;
use App\Http\Controllers\Public\TransporterProfileController;
use App\Http\Controllers\Public\TransportInquiryController;
use App\Http\Controllers\Public\TransportLandingController;
use App\Http\Controllers\Public\TransportLeadPortalController;
use App\Http\Controllers\Public\TransportMarketplaceController;
use App\Http\Controllers\Public\TransportReviewController;
use App\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Tenant\BugReportController;
use App\Http\Controllers\Tenant\ImportTemplateController;
use App\Http\Controllers\Tenant\TenantSelectorController;
use App\Http\Controllers\Transport\StripeConnectController;
use App\Http\Middleware\InitialiseTenantFromSession;
use App\Tenancy\TenantManager;
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
 * Publiczne pliki SEO — /sitemap.xml + /robots.txt. Indeksują tylko strony
 * marketingowe (landing, formularz zapytania, kalkulator, /t/{slug} dla
 * zweryfikowanych transporterów, /s/{slug} dla aktywnych stajni). Trasy z
 * tokenami (np. /transport/quote/...) oraz panele Filament są wykluczone w
 * robots.txt. Cache 1h — patrz SitemapController.
 */
Route::middleware('web')->get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('public.sitemap');
Route::middleware('web')->get('/robots.txt', [SitemapController::class, 'robots'])->name('public.robots');

/*
 * PWA offline fallback. Service worker (public/sw.js) precache'uje to
 * podczas install i serwuje gdy network-first padnie. Brak auth/middleware,
 * static-ish — sama strona z brand shellem i CTA "Spróbuj ponownie".
 */
Route::get('/offline', fn () => view('public.offline'))->name('offline');

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

// Pretty aliases dla pracowników do flow resetu hasła. Filament
// hostuje formularze w /app/password-reset/{request,reset}, ale
// /forgot-password jest krótszy do podyktowania przez telefon.
Route::get('/forgot-password', fn () => redirect('/app/password-reset/request'));
Route::get('/reset-password', fn () => redirect('/app/password-reset/request'));

/*
 * Laravel password broker (Password::sendResetLink) wymaga route name
 * `password.reset` żeby zbudować URL linka w mailu. Bez tego rzuca
 * RouteNotFoundException przy każdym wywołaniu sendResetLink (np. z
 * master admin „Force password reset" action na TenantResource).
 *
 * Route przyjmuje `{token}` w path + `?email=` w query (standard
 * Laravel email template), forwarduje do Filament app panel reset
 * page (tu lądują tenant owners). Master admin zwykle resetuje
 * tenanta — więc Filament's `app` panel jest właściwym targetem.
 */
Route::get('/password-reset/{token}', function (string $token) {
    return redirect()->to(url('/app/password-reset/reset', [
        'token' => $token,
        'email' => request()->query('email'),
    ]));
})->name('password.reset');

/*
 * Public demo — auto-login do tenant `demo` jako owner. Zero rejestracji,
 * dane resetowane co noc o 22:00 (hovera:demo:reset).
 *
 * Throttle żeby ktoś nie zassał tego endpointu jako auth bypass — jeden
 * adres IP może odpalić demo max 6 razy na minutę.
 */
Route::middleware(['web', 'throttle:6,1'])
    ->get('/demo', DemoLoginController::class)
    ->name('demo.login');

// In-demo role switcher — guarded by session('demo.is_demo'). 404 outside demo.
Route::middleware(['web'])
    ->get('/demo/as/{role}', [DemoLoginController::class, 'switchRole'])
    ->where('role', 'owner|admin|manager|instructor|employee|vet|viewer')
    ->name('demo.switch_role');

// Demo "client" view — loguje zwiedzającego do portalu klienta jako sample
// horse owner. Bez magic linka, instant dostęp. 404 poza demo.
Route::middleware(['web'])
    ->get('/demo/as-client', [DemoLoginController::class, 'loginAsClient'])
    ->name('demo.login_client');

/*
 * Public pricing page — `/pricing`. Czyta plany z central DB (is_public=true)
 * i renderuje porównawczy cennik z toggle miesięcznie/rocznie.
 *
 * Throttle 30/min — bot scraping protection bez blokowania normalnych
 * odwiedzających (cennik bywa odświeżany kilka razy podczas decyzji).
 */
Route::middleware(['web', 'throttle:30,1'])
    ->get('/pricing', [PricingController::class, 'show'])
    ->name('pricing.show');

// Cennik planów transportowych (Start/Pro/Business/Enterprise, 5 walut).
// Patrz docs/TRANSPORT.md §15.4 + hovera.app/produkt/transport/.
Route::middleware(['web', 'throttle:30,1'])
    ->get('/pricing/transport', [PricingController::class, 'showTransport'])
    ->name('pricing.transport');

/*
 * Strony prawne — regulamin, polityka prywatności, DPA. Renderowane
 * statycznie z lang files. Throttle 30/min broni przed scrapingiem.
 *
 * Linkowane z signupu (`/regulamin`, `/polityka-prywatnosci` w label
 * checkboxa zgody) — bez tych routów signup form daje 404 po kliknięciu
 * w link, co blokuje świadomą zgodę użytkownika (RODO art. 7).
 */
Route::middleware(['web', 'throttle:30,1'])->group(function () {
    Route::get('/regulamin', [LegalController::class, 'terms'])->name('legal.terms');
    Route::get('/polityka-prywatnosci', [LegalController::class, 'privacy'])->name('legal.privacy');
    Route::get('/dpa', [LegalController::class, 'dpa'])->name('legal.dpa');
    Route::get('/regulamin-marketplace', [LegalController::class, 'marketplace'])->name('legal.marketplace');

    // Publiczne centrum pomocy — dostępne BEZ logowania, indeksowane przez
    // wyszukiwarki. Te same markdownowe instrukcje co w /app/help + osadzone
    // dokumenty prawne. Kolejność tras istotna: /help/legal i /help/legal/{doc}
    // przed /help/{persona?}, żeby "legal" nie wpadało do regexu persony.
    Route::get('/help/legal/{doc?}', [HelpController::class, 'legal'])
        ->where('doc', 'terms|privacy|dpa')
        ->name('help.legal');
    Route::get('/help/{persona?}', [HelpController::class, 'show'])
        ->where('persona', 'owner|employee|specialist|client|transporter')
        ->name('help.show');
});

/*
 * Self-service signup — stajnia podaje 4 pola, dostaje 30-dniowy trial
 * z mailem zawierającym magic link do ustawienia hasła ownera.
 *
 * GET nie throttluje — same form. POST throttle 3/h z IP, hard limit
 * przeciw spam-rejestracjom.
 */
Route::middleware(['web'])
    ->prefix('signup')
    ->name('signup.')
    ->group(function () {
        Route::get('/', [SignupController::class, 'show'])->name('show');
        Route::post('/', [SignupController::class, 'submit'])
            ->middleware('throttle:3,60')
            ->name('submit');
        Route::get('/dziekujemy/{slug}', [SignupController::class, 'thanks'])
            ->where('slug', '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?')
            ->name('thanks');
    });

/*
 * Rejestracja Horse Owner'a — uproszczony flow vs stable/transporter signup
 * (3 pola, brak slug'a, brak wyboru planu). Owner = FREE tier, brak
 * trial'a, od razu active. Patrz `HorseOwnerRegistrationController`.
 *
 * Throttle 5/h z IP (vs 3/h dla stajni) bo consumer side ma większy ruch.
 */
Route::middleware(['web'])
    ->prefix('register/horse-owner')
    ->name('register.horse-owner.')
    ->group(function () {
        Route::get('/', [HorseOwnerRegistrationController::class, 'show'])->name('show');
        Route::post('/', [HorseOwnerRegistrationController::class, 'submit'])
            ->middleware('throttle:5,60')
            ->name('submit');
        Route::get('/dziekujemy/{slug}', [HorseOwnerRegistrationController::class, 'thanks'])
            ->where('slug', '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?')
            ->name('thanks');
    });

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
    Route::get('/start', [ImpersonationController::class, 'start'])->name('start');
    Route::post('/stop', [ImpersonationController::class, 'stop'])->name('stop');
});

/*
 * Central hovera billing — tenant owners pick a plan, Stripe Checkout
 * handles payment, Customer Portal handles upgrades/cancellations.
 *
 * Auth + tenant context come from the standard `web` + `auth` stack.
 * Role gate (owner/admin) is enforced inside BillingController.
 */
Route::middleware(['web', 'auth'])->prefix('app/billing')->name('billing.')->group(function () {
    Route::get('/', [BillingController::class, 'show'])->name('show');
    Route::post('/checkout', [BillingController::class, 'checkout'])->name('checkout');
    Route::get('/return', [BillingController::class, 'return'])->name('return');
    Route::post('/portal', [BillingController::class, 'portal'])->name('portal');
});

/*
 * Suspended-tenant landing page. RedirectIfTenantSuspended bounces every
 * /app/* request here when the master admin has flipped the tenant's
 * status — keeps the user informed without leaking half-loaded panels.
 */
Route::middleware(['web', 'auth'])
    ->get('/app/suspended', function () {
        $tenant = app(TenantManager::class)->current();

        return response()->view('tenant.suspended', ['tenant' => $tenant]);
    })
    ->name('tenant.suspended');

/*
 * Stripe webhook delivery target. CSRF excluded in bootstrap/app.php;
 * signature verification happens inside StripeBillingService. Throttled
 * defensively — Stripe normally fires <10/min per account.
 */
Route::post('/webhooks/stripe', StripeWebhookController::class)
    ->middleware(['throttle:60,1'])
    ->name('webhooks.stripe');

/*
 * Stripe Connect (per-transporter) webhook target. ODDZIELNY od central
 * /webhooks/stripe — w Stripe dashboardzie konfigurujemy dwa webhook'i
 * (platform events + connect events) z dwoma osobnymi signing secrets.
 * Patrz docs/TRANSPORT.md §15.6.
 */
Route::post('/webhooks/stripe-connect', StripeConnectWebhookController::class)
    ->middleware(['throttle:120,1'])
    ->name('webhooks.stripe_connect');

/*
 * Stripe Connect Express onboarding (transporter). Patrz docs/TRANSPORT.md §15.6.
 *   - /onboard   → create account if needed + AccountLink → redirect to Stripe KYC
 *   - /return    → po finiszu KYC: syncAccountStatus + redirect do /transport/settings
 *   - /refresh   → manual „Sprawdź status" przycisk
 *   - /dashboard → Stripe Express dashboard login link
 *
 * Wymagane: auth + tenant context + FULL_ADMIN role (sprawdzane w controllerze).
 */
Route::middleware(['web', 'auth'])
    ->prefix('/transport/stripe/connect')
    ->name('transport.stripe_connect.')
    ->group(function () {
        Route::get('/onboard', [StripeConnectController::class, 'onboard'])->name('onboard');
        Route::get('/return', [StripeConnectController::class, 'return'])->name('return');
        Route::post('/refresh', [StripeConnectController::class, 'refresh'])->name('refresh');
        Route::get('/dashboard', [StripeConnectController::class, 'dashboard'])->name('dashboard');
    });

/*
 * Przelewy24 webhook (CENTRAL hovera billing — NOT per-tenant). CSRF
 * excluded in bootstrap/app.php; signature (SHA384) verified inside
 * Przelewy24Service::verifyWebhook. Throttled defensively — P24 nie
 * powinno przesyłać więcej niż kilka na minutę.
 */
Route::post('/webhooks/przelewy24', Przelewy24WebhookController::class)
    ->middleware(['throttle:60,1'])
    ->name('webhooks.p24');

/*
 * P24 return URL — user-facing redirect po sesji P24. Status jest
 * pobierany async z webhooka (TO jest źródło prawdy), tutaj tylko
 * pokazujemy "Płatność w toku / OK" + redirect na /app/billing.
 */
Route::middleware(['web'])
    ->get('/payments/p24/return/{invoiceId}', [Przelewy24Controller::class, 'return'])
    ->name('payments.p24.return');

/*
 * Per-transporter P24 quote payments — patrz docs/TRANSPORT.md §15.5.
 *
 * Klient klika "Zapłać P24" na quote landing → P24 hosted checkout →
 * po sukcesie redirect (.return) + async notification (.webhook).
 * Webhook idzie z `tenant_slug` w URL'u żeby router znał kontekst
 * przed switch'em do tenant DB (slug jest publiczny — `t/{slug}`).
 *
 * Throttle 60/min — P24 zwykle wysyła <10/min per merchant.
 */
Route::middleware(['web'])
    ->prefix('/transport/p24')
    ->name('public.transport.p24.')
    ->group(function () {
        Route::get('/return/{tenant_slug}/{quote_id}', [Przelewy24QuoteReturnController::class, 'return'])
            ->where('tenant_slug', '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?')
            ->name('return');
        Route::post('/webhook/{tenant_slug}', [Przelewy24QuoteWebhookController::class, 'handle'])
            ->where('tenant_slug', '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?')
            ->middleware('throttle:60,1')
            ->name('webhook');
    });

/*
 * Per-transporter PayU quote payments — patrz docs/TRANSPORT.md §16.
 *
 * Analogiczne do `/transport/p24/*` ale z PayU OAuth + SHA256 signature.
 * Webhook + return URL z tenant_slug w pathnie żeby router znał kontekst
 * przed switch'em do tenant DB.
 */
Route::middleware(['web'])
    ->prefix('/transport/payu')
    ->name('public.transport.payu.')
    ->group(function () {
        Route::get('/return/{tenant_slug}/{quote_id}', [PayUQuoteReturnController::class, 'return'])
            ->where('tenant_slug', '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?')
            ->name('return');
        Route::post('/webhook/{tenant_slug}', [PayUQuoteWebhookController::class, 'handle'])
            ->where('tenant_slug', '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?')
            ->middleware('throttle:60,1')
            ->name('webhook');
    });

/*
 * P24 add-on purchases — Hovera-as-merchant flow. Patrz
 * docs/TRANSPORT.md §13 (master admin add-on purchases).
 *
 * Return: nazwany `admin.p24.addon.return` (używany przez
 * Przelewy24Service::chargeAddon do generowania urlReturn — niezależny
 * od /admin auth, bo P24 robi pure redirect bez session). Webhook:
 * `webhooks.p24.addon` — separated od `webhooks.p24` żeby routing był
 * one-line jasny (Invoice vs AddonPurchase).
 */
Route::middleware(['web'])
    ->get('/admin/p24/addon/return/{purchase_id}', [Przelewy24AddonReturnController::class, 'return'])
    ->where('purchase_id', '[0-9A-Za-z]{26}')
    ->name('admin.p24.addon.return');

Route::post('/webhooks/przelewy24/addon', Przelewy24AddonWebhookController::class)
    ->middleware(['throttle:60,1'])
    ->name('webhooks.p24.addon');

/*
 * PayU webhook (CENTRAL hovera billing — invoice flow). Patrz docs/TRANSPORT.md §16.
 *
 * CSRF excluded w bootstrap/app.php. Signature SHA256 z raw body + md5_key
 * weryfikowana wewnątrz PayUService::verifyWebhookSignature.
 */
Route::post('/webhooks/payu', PayUWebhookController::class)
    ->middleware(['throttle:60,1'])
    ->name('webhooks.payu');

/*
 * PayU add-on purchases — Hovera-as-merchant. Analogiczne do P24 add-on
 * flow. Return URL nazwany `admin.payu.addon.return` (używany przez
 * PayUService::chargeAddon do generowania continueUrl), webhook
 * `webhooks.payu.addon` — separated od `webhooks.payu` (Invoice vs AddonPurchase).
 */
Route::middleware(['web'])
    ->get('/admin/payu/addon/return/{purchase_id}', [PayUAddonReturnController::class, 'return'])
    ->where('purchase_id', '[0-9A-Za-z]{26}')
    ->name('admin.payu.addon.return');

Route::post('/webhooks/payu/addon', PayUAddonWebhookController::class)
    ->middleware(['throttle:60,1'])
    ->name('webhooks.payu.addon');

/*
 * PayU invoice return URL — user-facing redirect po sesji PayU. Status
 * jest pobierany async z webhooka (źródło prawdy), tu tylko user-facing
 * landing flash message.
 */
Route::middleware(['web'])
    ->get('/payments/payu/return/{invoice_id}', function (string $invoiceId) {
        return redirect('/app/billing')->with(
            'status',
            __('admin/billing.payu_return_message'),
        );
    })
    ->where('invoice_id', '[0-9A-Za-z]{26}')
    ->name('webhooks.payu.return');

/*
 * Import wizard helpers — pobranie szablonu .xlsx (klienci / konie) z jedną
 * przykładową linią. Trzymane w `web` + `auth` + InitialiseTenantFromSession
 * żeby route nie wymagał osadzania w panelu Filament; konieczny jest tylko
 * aktywny tenant w sesji (brak DB lookup, ale spójna konwencja).
 */
Route::middleware(['web', 'auth', InitialiseTenantFromSession::class])
    ->get('/app/import-wizard/template/{entity}', ImportTemplateController::class)
    ->where('entity', 'clients|horses')
    ->name('import-wizard.template');

// In-panel bug / suggestion reporter — POST z modala dostępnego z topbara
// w obu panelach (/app + /admin). Throttle żeby nikt nie spamował Todoist.
Route::middleware(['web', 'auth', 'throttle:20,1'])
    ->post('/bug-reports', [BugReportController::class, 'store'])
    ->name('bug-reports.store');

// Language switcher — redirects back to where the user came from.
Route::middleware('web')
    ->get('/locale/{locale}', LocaleController::class)
    ->where('locale', 'pl|en|fr|de|ru')
    ->name('locale.set');

// Master ads — dismiss + click tracking. Banner renderowany przez render hook
// (panel /app + /admin). CSRF chroni POST dismiss; click rejestruje GET +
// redirect na cta_url.
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/master-ads/{ad}/dismiss', [MasterAdController::class, 'dismiss'])->name('master-ads.dismiss');
    Route::get('/master-ads/{ad}/click', [MasterAdController::class, 'click'])->name('master-ads.click');
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
 * Public iCalendar feed for an instructor. Token-authenticated so calendar
 * apps (Google / Outlook / Apple) can subscribe without login. Lightly
 * throttled because the upstream apps poll every few hours.
 */
Route::middleware(['throttle:60,1'])
    ->get('/'.$publicPrefix.'/{slug}/calendar/instructor/{token}.ics',
        [InstructorCalendarController::class, 'show'])
    ->where(['slug' => $slugRegex, 'token' => '[A-Za-z0-9]{40,80}'])
    ->name('public.instructor_calendar');

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
 * Publiczny landing pod /transport — hero + embed formularz zapytania + Top 10
 * zweryfikowanych przewoźników. Patrz docs/TRANSPORT.md §16.
 *
 * Kolizja URL z panelem Filament `/transport` jest rozwiązana priorytetem
 * routingu Laravel: web.php rejestruje publiczne trasy przed Filament resolverem,
 * więc exact `/transport` trafia tu (do publicznego landing'i). Sub-route'y
 * panelu (`/transport/quotes`, `/transport/leads`, …) działają normalnie —
 * Filament obsługuje wszystko z prefixem `/transport/*` poza root.
 *
 * Auth-aware redirect w controllerze: transporter → panel, stable → zapytanie
 * z pre-fillem, guest/master/multi-tenant → render landing'a.
 */
Route::middleware('web')
    ->get('/transport', [TransportLandingController::class, 'show'])
    ->name('public.transport.landing');

/*
 * Publiczny katalog zweryfikowanych przewoźników pod /przewoznicy.
 * Trzecia ścieżka odkrywania marketplace'u — obok bezpośredniego /t/{slug}
 * i broadcast'u /transport/zapytanie. Filtry: województwo + nazwa firmy;
 * sort: ocena malejąco (default), nazwa, recency. Patrz docs/TRANSPORT.md §16.
 */
Route::middleware('web')
    ->get('/przewoznicy', [TransporterDirectoryController::class, 'index'])
    ->name('public.transporters.directory');

/*
 * Publiczna rejestracja firmy transportowej z dokumentami (PWL T1/T2 +
 * licencja zawodu + certyfikat kierowcy + świadectwo pojazdu + OC).
 * Pełny multi-section signup zastępujący lean `/signup?type=transporter`
 * dla firm które od razu chcą wgrać dokumenty. Patrz docs/TRANSPORT.md §15.
 *
 * Throttle TYLKO na POST submit (1/h/IP) — anti-abuse dla 6 file upload'ów
 * × 5MB = 30MB per signup. GET pozostaje 30/min bez blokowania normalnego
 * browsingu (user może wejść, opuścić, wrócić, poprawić → bez 429).
 */
Route::middleware(['web', 'throttle:30,1'])
    ->get('/przewoznicy/dolacz', [TransporterOnboardingController::class, 'show'])
    ->name('public.transport.onboarding.show');

// Throttle przez named limiter `transporter-onboarding` w AppServiceProvider:
// prod=1/h (anti-abuse dla 30MB file uploads), inne env=30/h (testing).
Route::middleware(['web', 'throttle:transporter-onboarding'])
    ->post('/przewoznicy/dolacz', [TransporterOnboardingController::class, 'submit'])
    ->name('public.transport.onboarding.submit');

Route::middleware('web')
    ->get('/przewoznicy/dolacz/dziekujemy/{slug}', [TransporterOnboardingController::class, 'thanks'])
    ->where('slug', '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?')
    ->name('public.transport.onboarding.thanks');

/*
 * Publiczny profil transportera pod /t/{slug} — marketing/SEO landing.
 * Renderuje tylko zweryfikowanych transporterów (isVerifiedTransporter),
 * pozostali zwracają 404 (niewidoczni dla świata). CTA na stronie linkuje
 * do /transport/zapytanie. Patrz docs/TRANSPORT.md §9 faza 7.
 */
Route::middleware('web')
    ->get('/t/{slug}', [TransporterProfileController::class, 'show'])
    ->where('slug', $slugRegex)
    ->name('public.transporter');

/*
 * Pre-rendered Open Graph image (1200x630 PNG) dla profilu transportera.
 * Linkowane w <meta property="og:image"> w profile.blade.php — kiedy
 * transporter wrzuca URL swojego profilu na FB/LinkedIn/X/Slack, unfurl
 * pokazuje branded card z nazwą + tagline + hovera footer zamiast
 * małego logo z pierwszego ujęcia. Patrz docs/TRANSPORT.md §9 faza 7.
 */
Route::middleware('web')
    ->get('/t/{slug}/og-image.png',
        [TransporterOgImageController::class, 'show'])
    ->where('slug', $slugRegex)
    ->name('public.transporter.og_image');

/*
 * Publiczna akceptacja oferty transportowej. URL przesyłany w mailu z PDFem;
 * klient klika "Akceptuję / Odrzucam" bez logowania. Token (48 znaków,
 * Str::random) jest jedyną poświadczeniową. Patrz docs/TRANSPORT.md §9 faza 3
 * punkt 4. POST throttle przeciwko brute-force zgadywaniu.
 */
Route::middleware(['web'])
    ->prefix('/transport/quote/{slug}/{token}')
    ->where(['slug' => $slugRegex, 'token' => '[A-Za-z0-9]{40,80}'])
    ->name('public.transport.')
    ->group(function () {
        Route::get('/', [QuoteAcceptanceController::class, 'show'])->name('quote');
        Route::post('/accept', [QuoteAcceptanceController::class, 'accept'])
            ->middleware('throttle:10,1')
            ->name('quote.accept');
        Route::post('/reject', [QuoteAcceptanceController::class, 'reject'])
            ->middleware('throttle:10,1')
            ->name('quote.reject');
    });

/*
 * Publiczny formularz zapytania transportowego. Anonim wpisuje od/do +
 * datę + horse count → tworzy transport_lead w status=open. LeadDispatcher
 * (faza 5+6 krok 4) podejmuje dispatch'em. Throttle POST 5/h z IP (mocne
 * anti-spam — lead = mail leci do transporterów). Patrz docs/TRANSPORT.md
 * §5.2 + krok 3 fazy 5+6.
 */
Route::middleware(['web'])
    ->prefix('/transport/zapytanie')
    ->name('public.transport.inquiry')
    ->group(function () {
        Route::get('/', [TransportInquiryController::class, 'show'])->name('');
        Route::post('/', [TransportInquiryController::class, 'submit'])
            ->middleware('throttle:5,60')
            ->name('.submit');
        Route::get('/dziekujemy/{lead}', [TransportInquiryController::class, 'thanks'])
            // Laravel HasUlids zwraca lowercase ulid (Crockford base32) —
            // wzorzec case-insensitive bo nie chcemy linków z różną wielkością
            // znaków psuć.
            ->where('lead', '[0-9A-Za-z]{26}')
            ->name('.thanks');
    });

/*
 * Portal klienta dla pojedynczego leada — permanent slug (UUID) z maila
 * wysyłanego po submit'cie /transport/zapytanie. Klient widzi swoje
 * zapytanie + napływające oferty. Patrz TransportLeadPortalController.
 */
Route::middleware(['web'])
    ->get('/transport/zapytanie/portal/{slug}', [TransportLeadPortalController::class, 'show'])
    ->where('slug', '[0-9a-fA-F-]{36}')
    ->name('public.transport.lead_portal');

/*
 * Publiczna giełda otwartych leadów transportowych — PR 8 z
 * docs/MARKETPLACE-ROADMAP.md. Lista status='open' leadów z filtrami
 * voivodeship/horses_count/date_window. Privacy: tylko województwa,
 * NIE pełne adresy.
 */
Route::middleware(['web'])
    ->get('/transport/marketplace', [TransportMarketplaceController::class, 'index'])
    ->name('public.transport.marketplace');

Route::middleware(['web'])
    ->post('/transport/marketplace/{lead}/claim', [TransportMarketplaceController::class, 'claim'])
    ->where('lead', '[0-9A-Za-z]{26}')
    ->name('public.transport.marketplace.claim');

/*
 * Publiczny formularz recenzji marketplace'u — magic link bez autoryzacji.
 * Invite generowany przez TransportReviewInviteService 14 dni po
 * preferred_date dla zaakceptowanych ofert. Token (48 znaków, sha256
 * w DB) jest jedyną poświadczeniową. POST throttle przeciwko
 * brute-force / spam. Patrz docs/TRANSPORT.md §12.
 */
Route::middleware(['web'])
    ->prefix('/transport/review')
    ->name('public.transport.review.')
    ->group(function () {
        Route::get('/dziekujemy', [TransportReviewController::class, 'thanks'])->name('thanks');
        Route::get('/wygasl', [TransportReviewController::class, 'expired'])->name('expired');
        Route::get('/{token}', [TransportReviewController::class, 'show'])
            ->where('token', '[A-Za-z0-9]{40,80}')
            ->name('show');
        Route::post('/{token}', [TransportReviewController::class, 'submit'])
            ->where('token', '[A-Za-z0-9]{40,80}')
            ->middleware('throttle:3,60')
            ->name('submit');
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
        Route::get('/horses/{horse}/photos/{photo}', [ClientPortalController::class, 'viewHorsePhoto'])->name('horses.photos.view');
        Route::get('/messages', [ClientPortalController::class, 'showMessages'])->name('messages.show');
        Route::get('/help', [ClientPortalController::class, 'showHelp'])->name('help.show');
        Route::get('/book', [ClientPortalController::class, 'showBooking'])->name('book.show');
        Route::post('/book', [ClientPortalController::class, 'submitBooking'])
            ->middleware('throttle:10,1')
            ->name('book.submit');
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

/*
 * Owner panel — CSV eksport listy faktur (C.7 z OWNER-STABLE-ROADMAP).
 * Web session (cookie z `/owner` panel login) — Auth jest w controller'ze.
 * Throttle defensywne (rzadko używane, ale eksport >100 faktur jest
 * heavier niż widoki listy).
 */
Route::middleware(['web', 'auth', 'throttle:10,1'])
    ->get('/owner/invoices/export.csv', InvoiceCsvExportController::class)
    ->name('owner.invoices.export-csv');
