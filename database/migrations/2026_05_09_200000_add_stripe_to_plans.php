<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan ↔ Stripe Price mapping. Filled in manually after the price object
 * is created in the Stripe Dashboard (one Product per plan, two Prices —
 * monthly + yearly). Null = plan nie jest płatny przez Stripe (Free,
 * Enterprise z custom contract).
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::connection('central')->table('plans', function (Blueprint $table) {
            $table->string('stripe_price_monthly_id')->nullable()->unique()->after('price_yearly_cents');
            $table->string('stripe_price_yearly_id')->nullable()->unique()->after('stripe_price_monthly_id');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('plans', function (Blueprint $table) {
            $table->dropUnique(['stripe_price_monthly_id']);
            $table->dropUnique(['stripe_price_yearly_id']);
            $table->dropColumn(['stripe_price_monthly_id', 'stripe_price_yearly_id']);
        });
    }
};
