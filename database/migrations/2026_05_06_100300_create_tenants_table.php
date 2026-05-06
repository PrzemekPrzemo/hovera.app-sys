<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('slug', 63)->unique();           // path segment + DB suffix
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('tax_id', 32)->nullable();       // NIP / VAT ID

            // Database isolation — physical per-tenant DB
            $table->string('db_host')->default('127.0.0.1');
            $table->unsignedSmallInteger('db_port')->default(3306);
            $table->string('db_name', 64)->unique();
            $table->string('db_username', 64)->unique();
            $table->text('db_password_encrypted');          // Laravel Crypt::encryptString()

            // Localisation
            $table->char('country', 2)->default('PL');
            $table->string('locale', 10)->default('pl');
            $table->string('timezone', 64)->default('Europe/Warsaw');
            $table->char('currency', 3)->default('PLN');

            // Subscription
            $table->foreignUlid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->enum('status', [
                'provisioning', 'trialing', 'active', 'past_due',
                'suspended', 'churned', 'deleted',
            ])->default('provisioning')->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspended_reason')->nullable();

            // Branding & per-tenant settings (visible on micro-site, in emails)
            $table->json('branding')->nullable();           // {logo_url, primary_color, ...}
            $table->json('settings')->nullable();           // free-form per-tenant config

            // Health & telemetry (denormalised for fast filtering)
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'last_activity_at']);
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
