<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PayU jako alternatywna bramka dla `addon_purchases` (Hovera-as-merchant).
 * Patrz docs/TRANSPORT.md §16.
 *
 * Architektura:
 *   - `provider` enum ('p24', 'payu') — default 'p24' żeby istniejące wiersze
 *     pozostały jednoznaczne. Master admin wybiera provider przy generowaniu
 *     linku.
 *   - PayU-specific tracking columns analogiczne do `p24_*` (session_id =
 *     PayU orderId, payment_url, paid_at).
 *
 * Hovera-level PayU credentials w `config('services.payu')` (env vars
 * PAYU_POS_ID + PAYU_OAUTH_CLIENT_ID/SECRET + PAYU_MD5_KEY).
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::connection('central')->table('addon_purchases', function (Blueprint $table) {
            // 'p24' jako default — historyczne wiersze zostają P24-only.
            $table->enum('provider', ['p24', 'payu'])
                ->default('p24')
                ->after('cancellation_reason');

            // PayU tracking — równolegle do p24_*. Pusta gdy provider='p24'.
            // payu_order_id jest unique bo PayU zwraca własny orderId w response z register.
            $table->string('payu_order_id', 64)->nullable()->unique()->after('p24_paid_at');
            $table->text('payu_payment_url')->nullable()->after('payu_order_id');
            $table->timestamp('payu_paid_at')->nullable()->after('payu_payment_url');
            $table->string('payu_ext_order_id', 64)->nullable()->after('payu_paid_at');

            $table->index('provider');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('addon_purchases', function (Blueprint $table) {
            $table->dropIndex(['provider']);
            $table->dropColumn(['provider', 'payu_order_id', 'payu_payment_url', 'payu_paid_at', 'payu_ext_order_id']);
        });
    }
};
