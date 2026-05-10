<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds source-tracking columns to Sanctum's personal_access_tokens.
 * Captured at issue-time by the AuthController / API stack so we can
 * surface "kto / skąd" in the master-admin tenant-tokens overview.
 *
 * Sanctum doesn't ship these out of the box — we extend the table
 * because the master admin needs forensic visibility for security reviews
 * (e.g. "this leaked token was issued from IP X, browser Y").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('personal_access_tokens', function (Blueprint $table) {
            $table->string('issued_ip', 45)->nullable()->after('expires_at');
            $table->string('issued_user_agent', 500)->nullable()->after('issued_ip');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['issued_ip', 'issued_user_agent']);
        });
    }
};
