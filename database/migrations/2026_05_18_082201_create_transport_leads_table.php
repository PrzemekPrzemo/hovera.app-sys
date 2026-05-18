<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centralna tabela leadów transportowych — patrz docs/TRANSPORT.md §4.1.
 *
 * Lead może mieć źródło: stajnia (originator_tenant_id), zalogowany user
 * (originator_user_id), lub anonim (originator_email + name + phone).
 * Tryb direct: targeted_transporter_ids nie jest puste (1–3). Broadcast:
 * NULL — dispatcher roześle do wszystkich z transport_service_areas
 * pasujących do voivodeships.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('transport_leads', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Originator
            $table->ulid('originator_tenant_id')->nullable()->index();
            $table->ulid('originator_user_id')->nullable()->index();
            $table->string('originator_email')->nullable();
            $table->string('originator_phone', 40)->nullable();
            $table->string('originator_name')->nullable();

            // Routing
            $table->enum('mode', ['direct', 'broadcast'])->index();
            $table->json('targeted_transporter_ids')->nullable();   // 1–3 transporter tenant_ids gdy mode=direct

            // Trasa
            $table->string('pickup_address');
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->string('pickup_voivodeship', 32);

            $table->string('dropoff_address');
            $table->decimal('dropoff_lat', 10, 7);
            $table->decimal('dropoff_lng', 10, 7);
            $table->string('dropoff_voivodeship', 32);

            // Termin
            $table->date('preferred_date');
            $table->time('preferred_time')->nullable();
            $table->boolean('flexible_date')->default(false);

            // Ładunek
            $table->unsignedTinyInteger('horse_count')->default(1);
            $table->json('horses')->nullable();                     // [{name, height_cm, weight_kg, papers_ok}]
            $table->text('notes')->nullable();

            // Lifecycle
            $table->enum('status', ['open', 'quoted', 'accepted', 'expired', 'cancelled'])
                ->default('open')
                ->index();
            $table->ulid('accepted_response_id')->nullable();        // FK do transport_lead_responses po akceptacji
            $table->timestamp('expires_at');

            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['pickup_voivodeship', 'dropoff_voivodeship']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('transport_leads');
    }
};
