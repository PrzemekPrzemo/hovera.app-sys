<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing-spec sync (hovera.app/produkt/transport/):
 *
 *  - GLOBAL add-ony — `plan_id` staje się NULLABLE (`is_global=true` ⇒ stosuje
 *    się do każdego planu transport, niezależnie od konkretnego planu).
 *  - `addon_type` rozróżnia jednorazowe (migracja danych, setup, onboarding live)
 *    od cyklicznych (extra driver/vehicle dopłata co miesiąc).
 *  - `prices_per_currency` — analogicznie do `plans` (PLN base + overlay 4 walut).
 *
 * Idempotentne — dodajemy kolumny bez modyfikacji istniejących wartości.
 * Add-ony plan-scoped (legacy) wciąż działają jako recurring monthly z plan_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('plan_addons', function (Blueprint $table) {
            // Typ rozliczenia: 'one_time' (jednorazowo, np. migracja) vs
            // 'recurring_monthly' (per miesiąc, dopłata do planu).
            $table->string('addon_type', 20)->default('recurring_monthly')->after('description');
            // Flag globalności — pomimo plan_id=NULL, dla read-side łatwiej
            // odpytać `where is_global=true` niż `where plan_id IS NULL`.
            $table->boolean('is_global')->default(false)->after('plan_id');
            // Multi-currency overlay — patrz add_prices_per_currency_to_plans.
            $table->json('prices_per_currency')->nullable()->after('price_yearly_cents');
            // Waluta bazowa add-onu (analogicznie do `plans.currency`).
            // Default PLN dla zgodności z istniejącymi rekordami.
            $table->string('currency', 3)->default('PLN')->after('description');
        });

        // Make plan_id nullable. Laravel 11+ z doctrine/dbal w composerze
        // natywnie obsługuje `change()`; działa na MySQL i SQLite.
        Schema::connection('central')->table('plan_addons', function (Blueprint $table) {
            $table->ulid('plan_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('plan_addons', function (Blueprint $table) {
            $table->dropColumn(['addon_type', 'is_global', 'prices_per_currency', 'currency']);
        });

        // Nie wracamy plan_id do NOT NULL — global add-ons mogłyby już
        // istnieć i rollback przerwałby uruchomione produkty. Manualne
        // czyszczenie via tinker przed `migrate:rollback`.
    }
};
