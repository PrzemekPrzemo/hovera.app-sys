<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant licznik numeracji ofert transportowych. Wzór analogiczny
 * do invoice_counters — atomic increment w transakcji `FOR UPDATE`.
 *
 * Scope key:
 *   'monthly:2026-05'  — reset miesięczny (domyślny dla OF/YYYY/MM/NNNN)
 *   'yearly:2026'      — reset roczny (opcjonalny)
 *   'global'           — bez resetu (opcjonalny)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_counters', function (Blueprint $table) {
            $table->string('scope', 32)->primary();
            $table->unsignedInteger('seq');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_counters');
    }
};
