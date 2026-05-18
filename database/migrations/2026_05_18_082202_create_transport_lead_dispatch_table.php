<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead × transporter — kto dostał powiadomienie o leadzie i kiedy. Patrz
 * docs/TRANSPORT.md §4.1. Dla trybu direct mamy 1–3 wiersze; broadcast może
 * mieć dziesiątki (wszyscy transporterzy obsługujący dane województwo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('transport_lead_dispatch', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('lead_id')
                ->constrained('transport_leads')
                ->cascadeOnDelete();
            $table->ulid('transporter_tenant_id')->index();

            // Kanały notyfikacji (najczęściej email + push; SMS opcjonalnie)
            $table->boolean('notified_email')->default(false);
            $table->boolean('notified_push')->default(false);
            $table->boolean('notified_in_app')->default(false);
            $table->timestamp('notified_at')->nullable();

            // Status leadu z perspektywy konkretnego transportera (denormalised
            // copy żeby panel transportera nie musiał JOIN-ować centralnie
            // i mógł szybko pokazać "nowe leady").
            $table->enum('view_status', ['unseen', 'seen', 'dismissed'])
                ->default('unseen')
                ->index();
            $table->timestamp('seen_at')->nullable();

            $table->timestamps();

            $table->unique(['lead_id', 'transporter_tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('transport_lead_dispatch');
    }
};
