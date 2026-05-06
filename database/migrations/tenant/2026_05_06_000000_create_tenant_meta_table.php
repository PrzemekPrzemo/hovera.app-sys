<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant DB skeleton — runs inside the per-stable database.
 * Other tenant tables (horses, clients, calendar, invoices, ...) come in
 * subsequent migrations. This one just records meta so we can verify
 * provisioning end-to-end and store schema_version.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_meta', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->json('value')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_meta');
    }
};
