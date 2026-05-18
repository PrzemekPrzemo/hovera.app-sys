<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-currency support na plany transportowe. Marketing spec wymaga
 * 5 walut: PLN (default), EUR, GBP, AUD, NZD. Struktura JSON:
 *
 *   {
 *     "EUR": {"monthly_cents": 5900, "yearly_cents": 63700},
 *     "GBP": {"monthly_cents": 4900, "yearly_cents": 52900},
 *     "AUD": {"monthly_cents": 9900, "yearly_cents": 106900},
 *     "NZD": {"monthly_cents": 10900, "yearly_cents": 117700}
 *   }
 *
 * `price_monthly_cents` + `currency` (default PLN) zostają jako kanoniczna
 * cena bazowa; `prices_per_currency` to overlay dla pozostałych walut.
 * Plan::priceFor($currency) konsumuje to.
 *
 * Patrz docs/TRANSPORT.md §15.4 oraz hovera.app/produkt/transport/ jako SoT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('plans', function (Blueprint $table) {
            $table->json('prices_per_currency')->nullable()->after('price_yearly_cents');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('plans', function (Blueprint $table) {
            $table->dropColumn('prices_per_currency');
        });
    }
};
