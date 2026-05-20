<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache średnich kursów NBP — patrz docs/MARKETPLACE-ROADMAP.md
 * "Multi-currency z NBP exchange rate". NBP publikuje tabelę A
 * (kursy średnie) codziennie ok. 11:45.
 *
 * Snapshot per (currency_code, effective_date) — unique, żeby
 * fetcher był idempotentny.
 *
 * `rate_to_pln` = ile PLN za 1 jednostkę danej waluty. Np. dla EUR
 * ~4.32 PLN. Konwersja PLN → EUR: `pln_amount / rate_to_pln`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('nbp_exchange_rates', function (Blueprint $table) {
            $table->id();

            $table->string('currency_code', 3)->index();
            $table->date('effective_date')->index();
            $table->decimal('rate_to_pln', 10, 4);

            $table->string('source', 16)->default('nbp_api');
            $table->json('raw_payload')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['currency_code', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('nbp_exchange_rates');
    }
};
