<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('plan_id')->constrained('plans');
            $table->enum('status', [
                'trialing', 'active', 'past_due', 'cancelled', 'incomplete',
            ])->default('trialing')->index();
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Provider refs (filled when integrations land)
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('p24_subscription_ref')->nullable()->unique();

            $table->timestamps();
        });

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('number')->unique();
            $table->char('currency', 3)->default('PLN');
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('vat_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->enum('status', ['draft', 'open', 'paid', 'void', 'uncollectible'])
                ->default('draft')->index();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // KSeF / Peppol placeholders for /admin billing
            $table->string('ksef_status')->nullable();
            $table->string('ksef_uuid')->nullable();
            $table->string('peppol_status')->nullable();
            $table->string('pdf_path')->nullable();

            $table->timestamps();
        });

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('invoice_id')->nullable()->constrained('billing_invoices')->nullOnDelete();
            $table->char('currency', 3)->default('PLN');
            $table->unsignedBigInteger('amount_cents');
            $table->string('provider', 32)->nullable();   // stripe, p24, manual
            $table->string('provider_ref')->nullable();
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded'])
                ->default('pending')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('subscriptions');
    }
};
