<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trzy nowe tabele dla "boarder management":
 *
 *   boarding_services     — cennik usług per stajnia (siano, woda,
 *                           sprzątanie boksu, transport, etc.)
 *   horse_boarding_services — pivot: który koń ma jakie usługi naliczane,
 *                           z opcjonalnym override ceny
 *   stable_activities     — log codziennych działań na koniu (odpowiednik
 *                           "kalendarza aktywności" widzianego przez właściciela)
 *
 * Po co? Żeby właściciel konia w portalu klienta mógł zobaczyć:
 *   1. Za co płaci — pełna lista usług + miesięczna szacunkowa kwota
 *   2. Co stajnia z koniem robi — historia karmień / czyszczeń / padoków
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boarding_services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('unit', 32)->default('szt.');     // 'szt.', 'kg', 'godz.', 'm-c'
            $table->string('frequency', 32)->index();         // BoardingFrequency
            $table->unsignedInteger('price_cents');           // cena netto za jednostkę
            $table->string('vat_rate', 8)->default('23');     // 23 / 8 / 5 / 0 / zw / np
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('horse_boarding_services', function (Blueprint $table) {
            // Pivot bez ulid id — Laravel BelongsToMany::attach() tego nie
            // wypełnia; trzymamy unique na parze + indices na obu FK.
            $table->foreignUlid('horse_id')->constrained('horses')->cascadeOnDelete();
            $table->foreignUlid('boarding_service_id')->constrained('boarding_services')->cascadeOnDelete();

            // Per-koń override ceny (gdy klient wynegocjował niższą stawkę
            // niż globalny cennik). Null = używamy boarding_services.price_cents.
            $table->unsignedInteger('price_override_cents')->nullable();
            // Per-koń override częstotliwości jeśli ten konkretny koń ma np.
            // 1 wypuszczenie na padok dziennie a inne 2.
            $table->decimal('quantity', 10, 3)->default(1);   // ile jednostek (np. 5kg siana)
            $table->date('starts_at')->nullable();             // od kiedy naliczane
            $table->date('ends_at')->nullable();               // do kiedy
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['horse_id', 'boarding_service_id'], 'horse_service_unique');
        });

        Schema::create('stable_activities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('horse_id')->constrained('horses')->cascadeOnDelete();
            $table->string('type', 32)->index();              // StableActivityType
            $table->timestamp('performed_at')->index();
            $table->string('performed_by', 120)->nullable();  // imię stajennego / "automatyzm"
            $table->string('summary', 200)->nullable();
            $table->text('details')->nullable();
            // Opcjonalna kwota gdy aktywność naliczyła dodatkowy koszt
            // (np. transport poza ryczałt, dodatkowe siano)
            $table->unsignedInteger('cost_cents')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by_central_user_id', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['horse_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stable_activities');
        Schema::dropIfExists('horse_boarding_services');
        Schema::dropIfExists('boarding_services');
    }
};
