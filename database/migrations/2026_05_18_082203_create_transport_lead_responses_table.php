<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oferty transporterów na lead. Patrz docs/TRANSPORT.md §4.2.
 *
 * Jeden lead × jeden transporter = jedna oferta (unique). Akceptacja jednej
 * przez zamawiającego ustawia status='accepted', a inne wiersze tego leadu
 * lecą do status='rejected' (QuoteAcceptanceService w fazie 6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('transport_lead_responses', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('lead_id')
                ->constrained('transport_leads')
                ->cascadeOnDelete();
            $table->ulid('transporter_tenant_id')->index();

            // Oferta
            $table->decimal('price_net', 10, 2);
            $table->decimal('price_gross', 10, 2);
            $table->string('currency', 3)->default('PLN');
            $table->decimal('distance_km', 8, 2)->nullable();

            $table->date('proposed_date');
            $table->time('proposed_time')->nullable();

            $table->text('terms')->nullable();
            $table->string('pdf_url')->nullable();
            $table->ulid('quote_id')->nullable();                 // FK do quotes w tenant DB transportera

            // Lifecycle
            $table->enum('status', ['pending', 'accepted', 'rejected', 'withdrawn'])
                ->default('pending')
                ->index();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->unique(['lead_id', 'transporter_tenant_id']);
            $table->index(['transporter_tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('transport_lead_responses');
    }
};
