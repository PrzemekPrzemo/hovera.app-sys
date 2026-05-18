<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Obszary obsługi transportera — patrz docs/TRANSPORT.md §3.2.
 *
 * Multi-row: jeden transporter ma N wierszy (po jednym per województwo).
 * LeadDispatcher w trybie broadcast wybiera transporterów z service_area
 * matchującym voivodeship z `transport_leads.pickup_voivodeship` LUB
 * `dropoff_voivodeship` (z uwzględnieniem adjacency, patrz §5.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('transport_service_areas', function (Blueprint $table) {
            $table->id();
            $table->ulid('transporter_tenant_id')->index();
            $table->string('voivodeship', 32);

            $table->timestamps();

            $table->unique(['transporter_tenant_id', 'voivodeship']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('transport_service_areas');
    }
};
