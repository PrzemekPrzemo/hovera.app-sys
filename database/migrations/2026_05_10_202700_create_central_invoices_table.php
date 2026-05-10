<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central invoices — faktury wystawiane przez hovera dla samych
 * stajni (płatność za hovera SaaS). Per-tenant invoices (stajnia →
 * klient) żyją w tenant DB w `App\Models\Tenant\Invoice`. Te dwa
 * obiegi są odrębne i celowo nie współdzielą tabeli.
 *
 * Generowane automatycznie z webhooka `checkout.session.completed`
 * po wybraniu planu — dla trial 2.0 to jest wpis "wybrali Pro po
 * trialu". KSeF push idzie osobnym jobem.
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::connection('central')->create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('number', 64)->unique();
            $table->string('plan_code', 32);
            $table->string('period', 16); // monthly|yearly|one_time
            $table->char('currency', 3)->default('PLN');
            $table->unsignedBigInteger('amount_cents')->default(0); // net
            $table->unsignedBigInteger('vat_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->timestamp('issued_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->string('stripe_invoice_id')->nullable()->index();
            $table->string('ksef_status', 32)->nullable();
            $table->string('ksef_reference', 128)->nullable();
            $table->json('snapshot')->nullable(); // immutable copy of tenant + plan data
            $table->timestamps();

            $table->index(['tenant_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('invoices');
    }
};
