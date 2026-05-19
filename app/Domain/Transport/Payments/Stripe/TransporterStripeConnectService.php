<?php

declare(strict_types=1);

namespace App\Domain\Transport\Payments\Stripe;

use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Services\TenantAuditLogger;
use InvalidArgumentException;
use RuntimeException;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Stripe Connect Express — transporter direct-charge. Patrz docs/TRANSPORT.md §15.6.
 *
 * Model „Connect Express":
 *   - Transporter ma WŁASNE Stripe konto (Express dashboard u Stripe; KYC,
 *     dane bankowe, VAT — wszystko po stronie Stripe).
 *   - Hovera = platforma — facylituje onboarding (AccountLink), tworzy
 *     Checkout Session na koncie transportera (header `Stripe-Account`).
 *   - Pieniądze idą BEZPOŚREDNIO do transportera (direct charge), Hovera
 *     NIE jest w cash flow. Opcjonalnie pobiera application_fee (commission)
 *     który Stripe automatycznie transferuje na konto platformy.
 *
 * Konstruktor przyjmuje secret + config, nie wstrzykujemy StripeBillingService
 * bo to inny world (platform account vs connected accounts) — żeby nikt
 * przez przypadek nie pomieszał kontekstów.
 *
 * Wszystkie publiczne metody są idempotentne / safe do retry'a. ApiErrorException
 * rzucamy callerowi (controller) — on decyduje co pokazać UI'owi.
 */
class TransporterStripeConnectService
{
    private StripeClient $stripe;

    public function __construct(
        private readonly string $secret,
        private readonly string $country = 'PL',
        private readonly float $applicationFeePercent = 0.0,
        private readonly ?TenantAuditLogger $audit = null,
    ) {
        if ($secret === '') {
            throw new InvalidArgumentException('Stripe secret key is empty — set STRIPE_SECRET in .env.');
        }
        if ($applicationFeePercent < 0 || $applicationFeePercent > 100) {
            throw new InvalidArgumentException('application_fee_percent must be between 0 and 100.');
        }

        $this->stripe = new StripeClient($secret);
    }

    /**
     * Tworzy Express account dla transportera. Idempotentne — jeśli tenant
     * już ma `stripe_connect_account_id`, zwraca istniejące konto bez
     * tworzenia drugiego.
     *
     * Persystencja: zapis robimy zawsze przed return (force-save), bo
     * caller nie ma gwarancji że Stripe call się powiódł — jak się nie powiódł,
     * leci wyjątek i tenant zostaje bez ID (clean state na retry).
     */
    public function createConnectAccount(Tenant $tenant): Account
    {
        $this->guardTransporter($tenant);

        if ($tenant->stripe_connect_account_id !== null) {
            return $this->stripe->accounts->retrieve($tenant->stripe_connect_account_id, []);
        }

        $ownerEmail = $this->ownerEmailFor($tenant);

        $account = $this->stripe->accounts->create([
            'type' => 'express',
            'country' => $this->country,
            'email' => $ownerEmail,
            'business_type' => 'company',
            'business_profile' => [
                'name' => $tenant->legal_name ?: $tenant->name,
                // Transport kont (NACE-zbliżony MCC: trucking)
                'mcc' => '4214',
                'product_description' => 'Usługi transportu drogowego koni i ładunków zwierzęcych.',
            ],
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
                // BLIK / Przelewy24 — wymagane dla PL. Stripe wymaga
                // `card_payments` jako bazy, BLIK/P24 jako dodatek.
                'blik_payments' => ['requested' => true],
                'p24_payments' => ['requested' => true],
            ],
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'tenant_slug' => (string) $tenant->slug,
            ],
        ]);

        $tenant->forceFill([
            'stripe_connect_account_id' => $account->id,
            'stripe_connect_status' => 'pending',
        ])->save();

        $this->audit?->record(
            'stripe.connect.account_created',
            'Tenant',
            (string) $tenant->id,
            ['stripe_account_id' => $account->id],
        );

        return $account;
    }

    /**
     * Generuje 1-strzałowy URL onboardingu Express (KYC u Stripe).
     * URL wygasa po ~kilku minutach — nie cache'ujemy. Caller (controller)
     * od razu robi redirect na zwrócony href.
     *
     * `return_url` = gdzie Stripe odeśle usera po finiszu (success lub
     * częściowy abort) — pokazujemy mu status. `refresh_url` = gdy link
     * wygaśnie w trakcie KYC, Stripe wraca tu — generujemy nowy link.
     */
    public function generateOnboardingLink(Tenant $tenant, string $returnUrl, string $refreshUrl): string
    {
        $this->guardTransporter($tenant);

        if ($tenant->stripe_connect_account_id === null) {
            // Lazy-create — pozwala wywołać generateOnboardingLink() bez
            // wcześniejszego osobnego createConnectAccount() (UX: jeden klik).
            $this->createConnectAccount($tenant);
            $tenant->refresh();
        }

        /** @var AccountLink $link */
        $link = $this->stripe->accountLinks->create([
            'account' => $tenant->stripe_connect_account_id,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        $this->audit?->record(
            'stripe.connect.onboarding_started',
            'Tenant',
            (string) $tenant->id,
            ['stripe_account_id' => $tenant->stripe_connect_account_id],
        );

        if (! is_string($link->url) || $link->url === '') {
            throw new RuntimeException('Stripe did not return an onboarding URL.');
        }

        return $link->url;
    }

    /**
     * Pull aktualnego stanu konta ze Stripe i synchronizacja `stripe_connect_status`
     * na Tenant'cie. Wywoływana:
     *   - po returnie z onboardingu (return_url)
     *   - z webhook'a `account.updated`
     *   - manual: master admin „Sprawdź status Stripe" w panelu
     *
     * Mapowanie:
     *   - charges_enabled=true → `enabled`
     *   - requirements.disabled_reason ≠ null → `restricted` lub `rejected`
     *     (rejected = `rejected.*` family, reszta restricted)
     *   - inaczej → `pending` (KYC w toku)
     */
    public function syncAccountStatus(Tenant $tenant): void
    {
        $this->guardTransporter($tenant);

        if ($tenant->stripe_connect_account_id === null) {
            return;
        }

        $account = $this->stripe->accounts->retrieve($tenant->stripe_connect_account_id, []);
        $newStatus = $this->mapAccountStatus($account);

        $update = ['stripe_connect_status' => $newStatus];

        // Jeśli teraz po raz pierwszy enabled — zapisz timestamp.
        if ($newStatus === 'enabled' && $tenant->stripe_connect_onboarded_at === null) {
            $update['stripe_connect_onboarded_at'] = now();
        }

        $tenant->forceFill($update)->save();

        $this->audit?->record(
            'stripe.connect.account_status_synced',
            'Tenant',
            (string) $tenant->id,
            ['status' => $newStatus, 'stripe_account_id' => $account->id],
        );
    }

    /**
     * Tworzy Stripe Checkout Session na koncie transportera (direct charge).
     *
     * Sposób direct-charge: `Stripe-Account` header (stripe_account opt) sprawia,
     * że PaymentIntent/Charge powstają na koncie transportera, klient widzi
     * descriptor transportera, pieniądze idą do transportera.
     *
     * application_fee_amount (gdy >0) = automatyczny transfer do platformy
     * (Hovera). Domyślnie 0 — Hovera bierze tylko abonament, nie cut z transakcji.
     *
     * Metadata: tenant_id + quote_id — webhook po sukcesie odnajdzie quote
     * i ustawi payment_completed_at=now() w bazie tenant'a.
     */
    public function createCheckoutSession(Quote $quote, Tenant $tenant, string $successUrl, string $cancelUrl): CheckoutSession
    {
        if (! $tenant->hasStripeConnectEnabled()) {
            throw new RuntimeException(
                "Tenant {$tenant->id} has no enabled Stripe Connect account — cannot create checkout."
            );
        }

        $grossCents = (int) round(((float) $quote->gross_total) * 100);
        if ($grossCents <= 0) {
            throw new InvalidArgumentException("Quote {$quote->id} has non-positive gross_total — cannot charge.");
        }

        $currency = strtolower((string) ($quote->currency ?? 'PLN'));

        $params = [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $grossCents,
                    'product_data' => [
                        'name' => "Transport — oferta {$quote->number}",
                        'description' => sprintf(
                            'Klient: %s · Trasa: %s → %s',
                            (string) $quote->customer_name,
                            (string) $quote->pickup_address,
                            (string) $quote->dropoff_address,
                        ),
                    ],
                ],
            ]],
            'customer_email' => $quote->customer_email ?: null,
            'payment_intent_data' => [
                'metadata' => [
                    'tenant_id' => (string) $tenant->id,
                    'quote_id' => (string) $quote->id,
                    'quote_number' => (string) $quote->number,
                ],
            ],
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'quote_id' => (string) $quote->id,
                'quote_number' => (string) $quote->number,
            ],
        ];

        // application_fee — Hovera bierze cut z transakcji. Default 0 (off).
        if ($this->applicationFeePercent > 0) {
            $fee = (int) round($grossCents * $this->applicationFeePercent / 100);
            if ($fee > 0 && $fee < $grossCents) {
                $params['payment_intent_data']['application_fee_amount'] = $fee;
            }
        }

        /** @var CheckoutSession $session */
        $session = $this->stripe->checkout->sessions->create(
            $params,
            // Direct-charge: action na connected account, nie na platformie.
            ['stripe_account' => $tenant->stripe_connect_account_id],
        );

        return $session;
    }

    /**
     * Express dashboard login link — używane gdy transporter chce zalogować
     * się do swojego Stripe konta (zobaczyć wypłaty, zmienić dane bankowe).
     * Link wygasa po kilku minutach, więc generujemy on-demand (przycisk
     * „Otwórz dashboard Stripe" w panelu).
     */
    public function createDashboardLoginLink(Tenant $tenant): string
    {
        $this->guardTransporter($tenant);

        if ($tenant->stripe_connect_account_id === null) {
            throw new RuntimeException("Tenant {$tenant->id} has no Stripe Connect account.");
        }

        $link = $this->stripe->accounts->createLoginLink($tenant->stripe_connect_account_id);

        if (! is_string($link->url) || $link->url === '') {
            throw new RuntimeException('Stripe did not return a dashboard URL.');
        }

        return $link->url;
    }

    /**
     * Mapuje stan Account → enum string. Wyciągnięte z syncAccountStatus
     * żeby było łatwo unit-testować (i ponownie użyć z webhook handler'a).
     */
    public function mapAccountStatus(Account $account): string
    {
        $chargesEnabled = (bool) ($account->charges_enabled ?? false);
        $detailsSubmitted = (bool) ($account->details_submitted ?? false);

        $requirements = $account->requirements ?? null;
        $disabledReason = is_object($requirements) && isset($requirements->disabled_reason)
            ? (string) $requirements->disabled_reason
            : (is_array($requirements) ? (string) ($requirements['disabled_reason'] ?? '') : '');

        if ($chargesEnabled) {
            return 'enabled';
        }

        if ($disabledReason !== '') {
            // Stripe disabled_reason: rejected.*, requirements.past_due,
            // requirements.pending_verification, platform_paused, ...
            if (str_starts_with($disabledReason, 'rejected')) {
                return 'rejected';
            }

            return 'restricted';
        }

        // Konto utworzone, KYC nie zakończony (details_submitted=false)
        // lub czeka na review Stripe.
        return $detailsSubmitted ? 'restricted' : 'pending';
    }

    /**
     * Lookup tenant po stripe_account_id z webhook'a Connect. Zwraca null
     * jeśli żaden tenant nie ma tego konta — webhook handler powinien wtedy
     * zalogować i 200-OK (Stripe nie próbuje retry'ować nieznanych eventów).
     */
    public static function findTenantByStripeAccount(string $stripeAccountId): ?Tenant
    {
        if ($stripeAccountId === '') {
            return null;
        }

        return Tenant::where('stripe_connect_account_id', $stripeAccountId)->first();
    }

    private function guardTransporter(Tenant $tenant): void
    {
        if (! $tenant->isTransporter()) {
            throw new InvalidArgumentException(
                "Stripe Connect Express is for transporters only — tenant {$tenant->id} is {$tenant->type?->value}."
            );
        }
    }

    private function ownerEmailFor(Tenant $tenant): ?string
    {
        $email = $tenant->memberships()
            ->where('role', 'owner')
            ->whereNull('revoked_at')
            ->orderBy('joined_at')
            ->limit(1)
            ->get()
            ->map(fn ($m) => $m->user?->email)
            ->filter()
            ->first();

        return is_string($email) ? $email : null;
    }
}
