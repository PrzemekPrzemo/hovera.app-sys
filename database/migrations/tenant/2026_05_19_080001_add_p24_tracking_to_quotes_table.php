<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P24 transaction tracking dla ofert (quotes) — patrz docs/TRANSPORT.md §15.5.
 *
 * Quote payments via P24 NIE używają tabeli `payments` (która wymaga
 * `client_id`, a quote ma tylko ad-hoc customer_name/email — bez FK do
 * tabeli clients). Trzymamy tracking inline na quote, mirror konwencji
 * z central.invoices.p24_*.
 *
 * Kolumny populated przez TransporterP24QuoteService:
 *   - p24_session_id: nasz quote.id (sessionId musi być unique per merchant)
 *   - p24_payment_url: hosted P24 checkout URL (kopiowany też do
 *     quote.payment_url przy create dla landing page'a)
 *   - p24_order_id: orderId z webhooka po succesie
 *   - p24_paid_at: timestamp z webhooka P24
 *
 * UWAGA: payment_completed_at (już istnieje) jest "user-facing" timestamp
 * — flipowany albo automatycznie (P24 webhook → tutaj) albo ręcznie
 * (przyciskiem "Oznacz jako opłacone"). p24_paid_at jest specyficzny
 * dla P24 i nie nadpisuje ręcznego oznaczenia ze Stripe / przelewu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('p24_session_id', 100)->nullable()->after('payment_notes');
            $table->text('p24_payment_url')->nullable()->after('p24_session_id');
            $table->string('p24_order_id', 32)->nullable()->after('p24_payment_url');
            $table->timestamp('p24_paid_at')->nullable()->after('p24_order_id');

            // Unique tylko gdy nie-null — wzór zgodny z central.invoices.
            $table->unique('p24_session_id', 'quotes_p24_session_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropUnique('quotes_p24_session_id_unique');
            $table->dropColumn(['p24_session_id', 'p24_payment_url', 'p24_order_id', 'p24_paid_at']);
        });
    }
};
