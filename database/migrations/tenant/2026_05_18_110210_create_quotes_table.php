<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oferty transportowe — patrz docs/TRANSPORT.md §3.2.
 *
 * Kolumny snapshot'owe (rate_per_km, fuel_*, vat_*) zamrażają stan stawek
 * z momentu wystawienia oferty — żeby zmiana settings nie modyfikowała
 * historycznych ofert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Numeracja + lifecycle
            $table->string('number', 32)->unique();
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired', 'withdrawn'])
                ->default('draft')
                ->index();

            // Klient
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->string('customer_company')->nullable();
            $table->string('customer_tax_id', 32)->nullable();
            $table->text('customer_address')->nullable();

            // Trasa
            $table->string('pickup_address');
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->string('dropoff_address');
            $table->decimal('dropoff_lat', 10, 7);
            $table->decimal('dropoff_lng', 10, 7);
            $table->date('preferred_date');
            $table->time('preferred_time')->nullable();
            $table->boolean('round_trip')->default(false);
            $table->boolean('loaded')->default(true);

            // Przypisanie zasobów (opcjonalne na draft, wymagane na akceptacji)
            $table->ulid('vehicle_id')->nullable()->index();
            $table->ulid('driver_id')->nullable()->index();

            // Distance + routing snapshot
            $table->decimal('distance_km', 8, 2);
            $table->unsignedInteger('duration_seconds');
            $table->string('routing_provider', 16);
            $table->text('polyline')->nullable();

            // Pricing snapshot
            $table->decimal('rate_per_km', 6, 2);
            $table->decimal('base_cost', 10, 2);
            $table->decimal('fuel_surcharge', 10, 2)->default(0);
            $table->decimal('minimum_adjustment', 10, 2)->default(0);
            $table->decimal('net_total', 10, 2);
            $table->decimal('vat_rate', 4, 2);
            $table->decimal('vat_amount', 10, 2);
            $table->decimal('gross_total', 10, 2);
            $table->char('currency', 3)->default('PLN');

            // Tekst kontraktu (klient widzi)
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();           // wewnętrzne, nie do PDF-a
            $table->date('valid_until')->nullable();

            // Public accept link
            $table->string('accept_token', 64)->nullable()->unique();

            // Lifecycle timestamps
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();

            // Linki do marketplace'u (central) — opcjonalne, gdy lead z formularza
            $table->ulid('lead_id')->nullable()->index();
            $table->ulid('response_id')->nullable()->index();

            // PDF
            $table->string('pdf_url')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
