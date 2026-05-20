<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner panel: lokalny "łącznik" do centralnego TransportLead.
 *
 * Każdy wiersz reprezentuje JEDNO zamówienie transportu złożone z panelu
 * /owner przez właściciela konia. `central_lead_id` wskazuje na rekord
 * `transport_leads` w central DB (źródło prawdy dla broadcastu, responses,
 * lifecycle'u). Tu trzymamy tylko owner-side metadata: który koń, mode,
 * lokalny status do wyświetlenia w "Moje zamówienia".
 *
 * Status enum mapuje 1:1 na TransportLead.status z dodatkowym `draft`
 * (lokalny stan zanim user kliknie "Wyślij zapytanie") — w MVP zawsze
 * tworzymy od razu z `open`, ale enum zostawiamy z draft'em żeby później
 * obsłużyć "Zapisz na później".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // ULID centralnego TransportLead'a — soft FK, bo central DB
            // i tenant DB są fizycznie rozłączne. Sprawdzanie integralności
            // robimy w aplikacji (przy listowaniu hydratujemy lead'a).
            $table->ulid('central_lead_id')->index();

            // FK lokalny do horse'a z `horses` table (owner reuse'uje
            // stable schema — patrz OwnerPanelProvider docblock).
            $table->foreignUlid('horse_id')
                ->nullable()
                ->constrained('horses')
                ->nullOnDelete();

            // Snapshot trasy — duplikujemy z central'a, żeby listowanie
            // "Moje zamówienia" nie wymagało N+1 join'ów z central'em.
            $table->string('pickup_address');
            $table->string('dropoff_address');
            $table->date('preferred_date');
            $table->time('preferred_time')->nullable();

            // Tryb kalkulacji — synch z CalculationMode (PR #294).
            $table->string('calculation_mode', 24)->default('one_way');

            // Lifecycle — sync z TransportLead.status + lokalny `draft`.
            $table->enum('status', ['draft', 'open', 'quoted', 'accepted', 'expired', 'cancelled'])
                ->default('open')
                ->index();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['status', 'preferred_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_orders');
    }
};
