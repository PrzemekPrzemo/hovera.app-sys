<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-transporter P24 quote auto-pay — patrz docs/TRANSPORT.md §15.5.
 *
 * Sam credential P24 (merchant_id, pos_id, crc, api_key) żyje już w
 * `tenants.settings.payments.p24` (ustawiane w panelu /app/payment-settings
 * — page `PaymentSettings`). Tutaj trzymamy tylko per-transport toggle
 * + opcjonalny override `quote_currency` (nie wszyscy transporterzy chcą
 * P24 dla każdej oferty — np. zagraniczne quoty w EUR są poza zasięgiem
 * polskiej bramki).
 *
 * Flow CreateQuote::afterCreate:
 *   1. quote.payment_url puste? → tak
 *   2. transport_settings.p24_quote_autopay_enabled = true? → tak
 *   3. tenants.settings.payments.p24 skonfigurowane? → tak
 *   4. quote.currency w whitelist (default: PLN)? → tak
 *   → twórz Payment + odpal P24PaymentProvider::initiate(),
 *     wstaw checkout URL do quote.payment_url
 *
 * Brak duplikacji credentials — single source of truth pozostaje w
 * `tenants.settings.payments.p24` (encrypted), tutaj tylko gate'y.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            $table->boolean('p24_quote_autopay_enabled')
                ->default(false)
                ->after('payment_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            $table->dropColumn('p24_quote_autopay_enabled');
        });
    }
};
