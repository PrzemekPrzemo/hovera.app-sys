<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\QuoteResource\Pages;

use App\Domain\Transport\Payments\PaymentUrlTemplate;
use App\Domain\Transport\Payments\Przelewy24\TransporterP24QuoteService;
use App\Domain\Transport\Payments\Stripe\TransporterStripeConnectService;
use App\Domain\Transport\Quotes\QuoteNumberGenerator;
use App\Filament\Transport\Resources\QuoteResource;
use App\Models\Tenant\Quote;
use App\Models\Tenant\TransportSettings;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;

    /**
     * Backlinki do central marketplace (lead_id, response_id) — przekazane
     * z session pre-fill (Calculator → Quote albo Lead inbox → Quote).
     * Nie są w form schema (klient nie powinien móc zmienić), więc trzymamy
     * je tu i wstrzykujemy w mutateFormDataBeforeCreate.
     */
    public ?string $pendingLeadId = null;

    public ?string $pendingResponseId = null;

    /**
     * Pre-fill from session — używane gdy user kliknie "Save as quote"
     * w `/transport/calculator` lub "Odpowiedz ofertą" w LeadResource.
     * Calculator/Lead zapisują pre-fill do session['transport.calc.pending'],
     * my je tu konsumujemy (i czyścimy żeby kolejny direct create dał
     * czysty form).
     */
    protected function fillForm(): void
    {
        $pending = session('transport.calc.pending');

        if (! is_array($pending)) {
            parent::fillForm();

            return;
        }

        session()->forget('transport.calc.pending');

        // Wyciągamy backlinki przed wlaniem do form'a (form schema ich nie ma)
        $this->pendingLeadId = isset($pending['lead_id']) ? (string) $pending['lead_id'] : null;
        $this->pendingResponseId = isset($pending['response_id']) ? (string) $pending['response_id'] : null;
        unset($pending['lead_id'], $pending['response_id']);

        $this->form->fill($pending);
    }

    /**
     * Numer generujemy w mutateFormDataBeforeCreate — żeby liczyć counter
     * dopiero po validacji formularza, ale przed insertem. Atomic transakcja
     * w QuoteNumberGenerator gwarantuje brak race condition'u. Tu też
     * wstrzykujemy backlinki lead_id / response_id z pre-fill'u.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['number'] = app(QuoteNumberGenerator::class)->next();

        if ($this->pendingLeadId) {
            $data['lead_id'] = $this->pendingLeadId;
        }
        if ($this->pendingResponseId) {
            $data['response_id'] = $this->pendingResponseId;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Kolejność preferencji payment_url (każdy guard early-returnuje gdy
        // $quote->payment_url jest już ustawione, więc pierwszy konfigurowany
        // wygrywa):
        //   1. Stripe Connect Express — server-side Checkout Session
        //      (docs/TRANSPORT.md §15.6)
        //   2. Przelewy24 autopay — hosted checkout transportera
        //      (docs/TRANSPORT.md §15.5)
        //   3. default_payment_url_template — fallback na własny URL transportera
        $this->applyStripeConnectCheckoutIfEnabled();
        $this->tryGenerateP24PaymentLink();
        $this->applyDefaultPaymentUrlIfBlank();

        app(TenantAuditLogger::class)->record(
            'quote.create',
            'Quote',
            (string) $this->record->getKey(),
            ['number' => $this->record->number],
        );
    }

    /**
     * P24 quote autopay — patrz docs/TRANSPORT.md §15.5.
     *
     * Jeśli transporter ma:
     *   1. `transport_settings.p24_quote_autopay_enabled = true`
     *   2. skonfigurowane creds w `tenants.settings.payments.p24`
     *   3. quote w PLN i z dodatnią kwotą
     * → generuje sesję P24 i ustawia quote.payment_url + payment_method_label.
     *
     * Soft-fail — jeśli P24 zwróci błąd (np. wygasł api_key), logujemy
     * + notification w panelu, ale create quote'a nie roluje wstecz.
     * Transporter może wrócić do edycji i wkleić URL ręcznie lub naprawić
     * settings.
     */
    private function tryGenerateP24PaymentLink(): void
    {
        /** @var Quote $quote */
        $quote = $this->record;

        if ($quote->payment_url) {
            return; // user already provided own URL — don't override
        }

        $settings = TransportSettings::current();
        if (! ($settings->p24_quote_autopay_enabled ?? false)) {
            return;
        }

        $tenant = app(TenantManager::class)->current();
        if ($tenant === null) {
            return;
        }

        $svc = app(TransporterP24QuoteService::class);
        if (! $svc->isConfigured($tenant)) {
            // Toggle on, ale brak creds — nie spamujemy logów, transporter
            // sam zobaczy w /app/payment-settings że nic nie wpisał.
            return;
        }

        try {
            $url = $svc->createPaymentSession($tenant, $quote);

            $quote->forceFill([
                'payment_url' => $url,
                'payment_method_label' => $quote->payment_method_label ?: 'Przelewy24',
            ])->save();
        } catch (\Throwable $e) {
            // Soft-fail — quote zostaje stworzona bez payment_url, fallback
            // do template'a (applyDefaultPaymentUrlIfBlank) ewentualnie
            // zadziała. Logujemy + notification dla user'a.
            Log::warning('P24 quote autopay failed', [
                'quote_id' => $quote->id,
                'tenant' => $tenant->slug,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->warning()
                ->title(__('transport/p24.notify.autopay_failed'))
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }

    /**
     * Direct-charge payments MVP — patrz docs/TRANSPORT.md §13.
     *
     * Jeśli transporter nie wkleił payment_url w formularzu, a w settings
     * ma ustawiony `default_payment_url_template` — rozwijamy placeholdery
     * z aktualnego Quote'u i zapisujemy. Robimy to po create (a nie w
     * mutateFormDataBeforeCreate), bo template potrzebuje wyliczonego
     * `number` + zapisanych `gross_total` / `customer_name`.
     */
    private function applyDefaultPaymentUrlIfBlank(): void
    {
        /** @var Quote $quote */
        $quote = $this->record;

        if ($quote->payment_url) {
            return;
        }

        $settings = TransportSettings::current();
        $template = trim((string) ($settings->default_payment_url_template ?? ''));
        if ($template === '') {
            return;
        }

        $quote->forceFill([
            'payment_url' => PaymentUrlTemplate::expand($template, $quote),
            'payment_method_label' => $quote->payment_method_label
                ?: ($settings->default_payment_method_label ?: null),
        ])->save();
    }

    /**
     * Stripe Connect Express — patrz docs/TRANSPORT.md §15.6.
     *
     * Jeśli transporter ma aktywne Stripe Connect Express konto i quote nie
     * ma jeszcze ręcznie wklejonego payment_url'a — generujemy server-side
     * Stripe Checkout Session na koncie transportera i wstawiamy redirect URL.
     *
     * Pieniądze idą BEZPOŚREDNIO do transportera (direct charge); webhook
     * `checkout.session.completed` po sukcesie ustawi payment_completed_at.
     *
     * Failure mode: jeśli Stripe API rzuci, logujemy + fallback do
     * default_payment_url_template (zwracamy false → caller wywoła template path).
     */
    private function applyStripeConnectCheckoutIfEnabled(): bool
    {
        /** @var Quote $quote */
        $quote = $this->record;

        if ($quote->payment_url) {
            return true; // już ustawione ręcznie, nie nadpisujemy
        }

        $tenant = app(TenantManager::class)->current();
        if ($tenant === null || ! $tenant->hasStripeConnectEnabled()) {
            return false;
        }

        try {
            $session = app(TransporterStripeConnectService::class)->createCheckoutSession(
                quote: $quote,
                tenant: $tenant,
                successUrl: url("/transport/quote/{$tenant->slug}/{$quote->accept_token}?paid=1"),
                cancelUrl: url("/transport/quote/{$tenant->slug}/{$quote->accept_token}?cancelled=1"),
            );

            if (! is_string($session->url) || $session->url === '') {
                return false;
            }

            $quote->forceFill([
                'payment_url' => $session->url,
                'payment_method_label' => __('transport/stripe_connect.payment_method_label'),
            ])->save();

            return true;
        } catch (\Throwable $e) {
            // Nie blokujemy create — quote zostaje bez Stripe URL, transporter
            // zobaczy w UI że można wkleić linka ręcznie. Logujemy żeby ops
            // wiedział że konfiguracja Stripe transportera się rozjechała.
            Log::warning('Stripe Connect Checkout creation failed; falling back', [
                'tenant_id' => $tenant->id,
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
