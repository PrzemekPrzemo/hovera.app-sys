<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding fee — obowiązkowa jednorazowa opłata wdrożeniowa dla każdego
 * płatnego planu (poza Free). Master admin konfiguruje per plan w
 * /admin/plans. Stripe Checkout dorzuca tę pozycję jako one-time line_item
 * obok subskrypcji (mode=subscription wspiera mixed line_items).
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
            $table->unsignedBigInteger('onboarding_fee_cents')->nullable()->after('price_yearly_cents');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('plans', function (Blueprint $table) {
            $table->dropColumn('onboarding_fee_cents');
        });
    }
};
