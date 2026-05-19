<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PayU jako alternatywna bramka dla `invoices` (Hovera-as-merchant —
 * Hovera inkasuje od tenantów za SaaS billing). Patrz docs/TRANSPORT.md §16.
 *
 * Analogiczne do p24_* kolumn dodanych w
 * `2026_05_10_220000_extend_invoices_for_p24_ksef.php`.
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
            $table->string('payu_order_id', 64)->nullable()->after('p24_paid_at');
            $table->string('payu_ext_order_id', 64)->nullable()->after('payu_order_id');
            $table->text('payu_payment_url')->nullable()->after('payu_ext_order_id');
            $table->timestamp('payu_paid_at')->nullable()->after('payu_payment_url');

            $table->unique('payu_order_id', 'invoices_payu_order_id_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_payu_order_id_unique');
            $table->dropColumn(['payu_order_id', 'payu_ext_order_id', 'payu_payment_url', 'payu_paid_at']);
        });
    }
};
