<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wsparcie dla PayU recurring (subscription billing) — analogiczne do
 * `stripe_subscription_id` na tej samej tabeli. Pierwsza płatność z
 * `recurring=FIRST` zapisuje token karty (encrypted via cast), kolejne
 * cykliczne charge'y idą przez `recurring=STANDARD` + CARD_TOKEN.
 *
 * Patrz docs/BILLING.md (sekcja PayU recurring) i PayUService.
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::connection('central')->table('subscriptions', function (Blueprint $table): void {
            // Encrypted card token (PayU `paymentMethods[0].value` z FIRST webhook).
            // varchar(512) bo encrypted payload jest dłuższy niż raw token.
            $table->string('payu_recurring_token', 512)->nullable()->after('p24_subscription_ref');

            // Display-only: zamaskowana karta dla user UI ("**** **** **** 1234").
            $table->string('payu_card_mask', 32)->nullable()->after('payu_recurring_token');
            $table->string('payu_card_brand', 32)->nullable()->after('payu_card_mask');
            $table->date('payu_card_expires_at')->nullable()->after('payu_card_brand');

            // Dunning state machine: status ostatniej próby + licznik
            // failed attempts (resetowany po success). Stripe-like retry
            // policy: 3 → 7 dni, suspend po 3. nieudanej.
            $table->string('payu_last_charge_status', 32)->nullable()->after('payu_card_expires_at');
            $table->timestamp('payu_last_failed_at')->nullable()->after('payu_last_charge_status');
            $table->unsignedSmallInteger('payu_failed_attempts')->default(0)->after('payu_last_failed_at');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn([
                'payu_recurring_token',
                'payu_card_mask',
                'payu_card_brand',
                'payu_card_expires_at',
                'payu_last_charge_status',
                'payu_last_failed_at',
                'payu_failed_attempts',
            ]);
        });
    }
};
