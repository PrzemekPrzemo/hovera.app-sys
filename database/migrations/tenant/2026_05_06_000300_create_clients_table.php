<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->enum('type', ['individual', 'family', 'organisation'])->default('individual')->index();

            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->string('tax_id', 32)->nullable();   // NIP for B2B / VAT id

            // Address
            $table->string('street')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 120)->nullable();
            $table->char('country', 2)->default('PL');

            // RODO / GDPR
            $table->timestamp('rodo_consent_at')->nullable();
            $table->string('rodo_consent_source', 60)->nullable();

            // Optional link to a global Hovera user account (e.g. parent
            // self-service portal). Soft FK to the central users table.
            $table->string('central_user_id', 26)->nullable()->index();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
