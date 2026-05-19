<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PayU transaction tracking dla ofert (quotes) — patrz docs/TRANSPORT.md §16.
 *
 * Analogiczne do `p24_*` kolumn. Quote payments via PayU NIE używają tabeli
 * `payments` (która wymaga `client_id`); trzymamy tracking inline na quote.
 *
 * Kolumny populated przez TransporterPayUQuoteService:
 *   - payu_order_id: PayU orderId zwrócony przez POST /api/v2_1/orders
 *   - payu_ext_order_id: nasz id wysłany jako `extOrderId` (= quote.id)
 *   - payu_payment_url: redirectUri z PayU response (hosted checkout)
 *   - payu_paid_at: timestamp z webhooka PayU (status=COMPLETED)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('payu_order_id', 64)->nullable()->after('p24_paid_at');
            $table->string('payu_ext_order_id', 64)->nullable()->after('payu_order_id');
            $table->text('payu_payment_url')->nullable()->after('payu_ext_order_id');
            $table->timestamp('payu_paid_at')->nullable()->after('payu_payment_url');

            $table->unique('payu_order_id', 'quotes_payu_order_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropUnique('quotes_payu_order_id_unique');
            $table->dropColumn(['payu_order_id', 'payu_ext_order_id', 'payu_payment_url', 'payu_paid_at']);
        });
    }
};
