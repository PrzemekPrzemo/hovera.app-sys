<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('email')->unique();
            $table->string('name');
            $table->string('password');
            $table->string('locale', 10)->default('pl');
            $table->string('timezone', 64)->default('Europe/Warsaw');

            // 2FA (TOTP)
            $table->text('two_factor_secret')->nullable();   // encrypted
            $table->text('two_factor_recovery_codes')->nullable(); // encrypted JSON
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // Master admin flag — gates access to /admin
            $table->boolean('is_master_admin')->default(false)->index();

            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
