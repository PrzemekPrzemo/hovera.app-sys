<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-stable list of vets and farriers (kowali / weterynarzy).
 *
 * Modeled as a single table with `type` discriminator (vet|farrier)
 * — one resource, one navigation entry, two filtered lists. Most
 * specialists are external contractors (not Hovera users); the
 * optional `central_user_id` lets stables link an internal employee
 * who also happens to be a vet/farrier so they can later see their
 * own assigned tasks in /app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialists', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // 'vet' | 'farrier' — two specialist kinds for now. Adding
            // more (dentist, physio) in the future = just extend this enum.
            $table->string('type', 16)->index();

            // Optional FK to central.users — for staff members who log in.
            // Soft string FK (no foreign key constraint, central DB is
            // a different connection in our multi-tenant setup).
            $table->string('central_user_id', 26)->nullable()->index();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('color', 7)->nullable();   // calendar tint

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });

        // Health records: link to specialist instead of free-text
        // performed_by. Both columns coexist — performed_by stays for
        // historical entries and ad-hoc notes (e.g. "asystent kowala").
        Schema::table('health_records', function (Blueprint $table) {
            $table->ulid('specialist_id')->nullable()->after('performed_by')->index();
        });

        // Stable activity (feeding/grooming/turnout/transport): the
        // groom is usually staff but might also be a contracted person.
        // Same pattern — keep performed_by free-text + add FK.
        Schema::table('stable_activities', function (Blueprint $table) {
            $table->ulid('specialist_id')->nullable()->after('performed_by')->index();
        });
    }

    public function down(): void
    {
        Schema::table('stable_activities', function (Blueprint $table) {
            $table->dropColumn('specialist_id');
        });
        Schema::table('health_records', function (Blueprint $table) {
            $table->dropColumn('specialist_id');
        });
        Schema::dropIfExists('specialists');
    }
};
