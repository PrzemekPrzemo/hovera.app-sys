<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructors', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Optional link to a Hovera user account. Allows mapping an
            // instructor in the calendar to "this stable's TenantMembership"
            // so we can later auto-pull their name / email. Soft FK to
            // central users table.
            $table->string('central_user_id', 26)->nullable()->index();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->unsignedInteger('hourly_rate_cents')->nullable();
            $table->string('color', 7)->nullable();   // hex tint in calendar UI
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructors');
    }
};
