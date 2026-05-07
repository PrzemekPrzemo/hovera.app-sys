<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Counters per "scope" — np. "FV:2026" gdy reset roczny,
        // "FV:2026-05" gdy miesięczny. Atomic increment via transaction.
        Schema::create('invoice_counters', function (Blueprint $table) {
            $table->string('scope', 64)->primary();
            $table->unsignedInteger('seq')->default(0);
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Numeracja widoczna na fakturze (puste do wystawienia).
            $table->string('number', 64)->nullable()->unique();

            $table->string('kind', 32)->index();           // fv | fv_proforma | fv_korekta
            $table->string('status', 32)->index();         // draft | issued | paid | overdue | void | cancelled

            $table->foreignUlid('client_id')->constrained('clients')->restrictOnDelete();

            // Powiązania:
            //  - related_payment_id: gdy FV powstała z online płatności
            //  - related_pass_id: gdy FV jest za zakup karnetu (auto-FV)
            //  - corrects_invoice_id: gdy FV_KOREKTA — wskazuje fakturę
            //    którą koryguje
            $table->string('related_payment_id', 26)->nullable()->index();
            $table->string('related_pass_id', 26)->nullable()->index();
            $table->string('corrects_invoice_id', 26)->nullable()->index();

            // Snapshot sprzedawcy (denormalizujemy w momencie wystawienia,
            // żeby zmiana danych stajni nie rewrote'owała historycznych FV)
            $table->string('seller_name');
            $table->string('seller_nip', 16)->nullable();
            $table->string('seller_address')->nullable();
            $table->string('seller_postal_code', 16)->nullable();
            $table->string('seller_city', 120)->nullable();
            $table->string('seller_country', 2)->default('PL');

            // Snapshot nabywcy (osoba fizyczna lub firma)
            $table->string('buyer_name');
            $table->string('buyer_nip', 16)->nullable();   // NULL dla osób fizycznych
            $table->string('buyer_address')->nullable();
            $table->string('buyer_postal_code', 16)->nullable();
            $table->string('buyer_city', 120)->nullable();
            $table->string('buyer_country', 2)->default('PL');

            // Daty
            $table->date('issued_at')->nullable()->index();   // data wystawienia (= numer)
            $table->date('sale_date')->nullable();            // data sprzedaży (zwykle == issued_at)
            $table->date('due_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();

            // Kwoty (zaokrąglane na poziomie items, sumy denormalizowane)
            $table->char('currency', 3)->default('PLN');
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('vat_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);

            // KSeF placeholders (PR 4 wypełni)
            $table->string('ksef_status', 32)->nullable();    // pending | sent | accepted | rejected
            $table->string('ksef_reference', 191)->nullable();
            $table->timestamp('ksef_sent_at')->nullable();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'issued_at']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invoice_id')->constrained('invoices')->cascadeOnDelete();

            $table->unsignedSmallInteger('position')->default(1);
            $table->string('name');
            $table->string('description')->nullable();

            $table->decimal('quantity', 10, 3)->default(1);
            $table->string('unit', 16)->default('szt.');

            // VAT rate jako string żeby obsłużyć "zw" / "np" / "0"
            $table->string('vat_rate', 8)->default('23');

            // Wszystko w groszach (cents) żeby unikać błędów floating point.
            // unit_price_cents zawsze NETTO; brutto / VAT obliczamy.
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
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_counters');
    }
};
