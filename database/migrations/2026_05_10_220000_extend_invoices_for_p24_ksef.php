<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rozszerza central.invoices (utworzone w Trial 2.0 PR #162) o pola
 * wymagane do dwóch nowych integracji:
 *   - Przelewy24 link "opłać fakturę" (sesja P24, URL, status)
 *   - KSeF push (XML signed, reference, status, response log)
 *
 * Plus pola ogólne wcześniej brakujące: kind, vat_rate, status, due_at,
 * subscription_id, payload_snapshot (alias snapshot), soft deletes.
 *
 * Nazwa tego migracyjnego pliku zachowuje stary identyfikator
 * `extend_billing_invoices_for_p24_ksef` z kontekstu PR #163, ale
 * po rebase'ie celuje w central.invoices (Trial 2.0 wygrał race).
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::connection('central')->table('invoices', function (Blueprint $table) {
            // Subscription + faktura kind (regular/proforma/correction)
            $table->foreignUlid('subscription_id')->nullable()->after('tenant_id');
            $table->string('kind', 16)->default('regular')->after('number');

            // VAT rate explicit (PR #162 trzymał tylko vat_cents — bez rate)
            $table->unsignedTinyInteger('vat_rate')->default(23)->after('total_cents');

            // Status zgodny ze Stripe (draft|open|paid|void|uncollectible)
            $table->string('status', 24)->default('draft')->after('vat_rate');

            // Due date dla terminów płatności
            $table->timestamp('due_at')->nullable()->after('issued_at');

            // P24 — link "opłać fakturę"
            $table->string('p24_session_id', 100)->nullable()->after('stripe_invoice_id')->unique();
            $table->text('p24_payment_url')->nullable()->after('p24_session_id');
            $table->unsignedBigInteger('p24_order_id')->nullable()->after('p24_payment_url');
            $table->timestamp('p24_paid_at')->nullable()->after('p24_order_id');

            // KSeF push — szczegóły poza istniejącym ksef_status/ksef_reference
            $table->string('ksef_uuid', 64)->nullable()->after('ksef_reference');
            $table->timestamp('ksef_pushed_at')->nullable()->after('ksef_uuid');
            $table->json('ksef_last_response')->nullable()->after('ksef_pushed_at');

            // Peppol (przyszła integracja UE)
            $table->string('peppol_status', 24)->nullable()->after('ksef_last_response');

            // Immutable snapshot tenanta przy wystawieniu (alias dla `snapshot`)
            $table->json('payload_snapshot')->nullable()->after('snapshot');

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('invoices', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'subscription_id', 'kind', 'vat_rate', 'status', 'due_at',
                'p24_session_id', 'p24_payment_url', 'p24_order_id', 'p24_paid_at',
                'ksef_uuid', 'ksef_pushed_at', 'ksef_last_response',
                'peppol_status', 'payload_snapshot',
            ]);
        });
    }
};
