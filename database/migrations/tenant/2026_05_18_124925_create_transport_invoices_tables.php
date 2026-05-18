<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transport-specific invoicing — osobny od stable invoices (hovera dla
 * stajni). Patrz docs/TRANSPORT.md §9 faza 3 + feedback prod (krok C).
 *
 * Decyzja: separate tables (NIE współdzielimy z invoicing.php), bo:
 *  - Transport FV ma fields specyficzne (quote_id, distance, route snapshot)
 *  - Numeracja: FT/{YYYY}/{MM}/{seq:4} vs hovera FV/...
 *  - Klient w transport: ad-hoc (z Quote), nie z Client model w stajni
 *  - PDF / KSeF mapping differs subtelnie (CMR fields)
 *
 * Reuse pattern: kolumny seller_/buyer_ snapshot, status/kind enum,
 * cents-only money — bo to się sprawdza w stajni.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_invoice_counters', function (Blueprint $table) {
            $table->string('scope', 64)->primary();
            $table->unsignedInteger('seq')->default(0);
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('transport_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Numeracja (pusta do issued)
            $table->string('number', 64)->nullable()->unique();
            $table->string('kind', 32)->index();          // fv | fv_proforma | fv_korekta
            $table->string('status', 32)->index();        // draft | issued | paid | overdue | void | cancelled

            // Link do oferty (jeśli FV powstała z accepted Quote)
            $table->ulid('quote_id')->nullable()->index();
            // Link do central response (gdy z marketplace lead)
            $table->ulid('response_id')->nullable()->index();
            // Korekta wskazuje fakturę którą koryguje
            $table->ulid('corrects_invoice_id')->nullable()->index();

            // Snapshot sprzedawcy (transporter)
            $table->string('seller_name');
            $table->string('seller_nip', 16)->nullable();
            $table->string('seller_address')->nullable();
            $table->string('seller_postal_code', 16)->nullable();
            $table->string('seller_city', 120)->nullable();
            $table->string('seller_country', 2)->default('PL');
            $table->string('seller_iban', 40)->nullable();
            $table->string('seller_bank_name', 120)->nullable();

            // Snapshot nabywcy (klient — osoba fiz. lub firma)
            $table->string('buyer_name');
            $table->string('buyer_nip', 16)->nullable();
            $table->string('buyer_address')->nullable();
            $table->string('buyer_postal_code', 16)->nullable();
            $table->string('buyer_city', 120)->nullable();
            $table->string('buyer_country', 2)->default('PL');
            $table->string('buyer_email')->nullable();

            // Snapshot trasy (do CMR / opis usługi)
            $table->string('pickup_address')->nullable();
            $table->string('dropoff_address')->nullable();
            $table->date('service_date')->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->ulid('vehicle_id')->nullable()->index();
            $table->ulid('driver_id')->nullable()->index();

            // Daty
            $table->date('issued_at')->nullable()->index();
            $table->date('sale_date')->nullable();
            $table->date('due_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();

            // Pieniądze (cents → bez floating-point błędów)
            $table->char('currency', 3)->default('PLN');
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('vat_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);

            // KSeF — placeholder pod C3, populated przez CentralKsefService
            $table->string('ksef_status', 32)->nullable();
            $table->string('ksef_reference', 191)->nullable();
            $table->timestamp('ksef_sent_at')->nullable();

            $table->text('notes')->nullable();
            $table->string('pdf_url')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'due_at']);
            $table->index(['status', 'issued_at']);
        });

        Schema::create('transport_invoice_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invoice_id')
                ->constrained('transport_invoices')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('position')->default(1);
            $table->string('name');
            $table->string('description')->nullable();

            $table->decimal('quantity', 10, 3)->default(1);
            $table->string('unit', 16)->default('usł.');   // usługa transportowa

            // VAT jako string ('zw'/'np'/'0'/'8'/'23')
            $table->string('vat_rate', 8)->default('23');

            // Cents (netto)
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('net_cents');
            $table->unsignedBigInteger('vat_cents');
            $table->unsignedBigInteger('total_cents');

            $table->timestamps();
            $table->index(['invoice_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_invoice_items');
        Schema::dropIfExists('transport_invoices');
        Schema::dropIfExists('transport_invoice_counters');
    }
};
