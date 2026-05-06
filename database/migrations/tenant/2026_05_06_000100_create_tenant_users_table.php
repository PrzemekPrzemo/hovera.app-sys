<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant user references — soft FK to central users table.
 * We keep a copy in the tenant DB so all queries can be done without
 * cross-database joins. Sync managed by App\Tenancy\UserSync (later).
 *
 * `central_user_id` is the ULID from the central users table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('central_user_id', 26)->unique();
            $table->string('email')->index();
            $table->string('name');
            $table->string('role', 32)->default('viewer');
            $table->json('permissions')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
    }
};
