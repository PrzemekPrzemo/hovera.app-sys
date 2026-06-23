<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR O5 Channel B (epic 1.2) — łącznik między per-stable `Specialist`
 * (kontakt w tenant DB) a cross-tenant `ExternalSpecialist` (tożsamość
 * auth w central DB).
 *
 * Gdy stajnia dodaje kontakt lokalny z e-mailem, który już istnieje jako
 * zarejestrowany ExternalSpecialist, autolink wypełnia tę kolumnę — dzięki
 * temu wątki Channel B (epic 1.3+) i badge „zweryfikowany" wiedzą, że ten
 * lokalny kontakt to realne konto specjalisty w Hoverze.
 *
 * Soft string FK (brak FK constraint) — central DB to osobne połączenie
 * w multi-tenant setupie, tak samo jak `central_user_id` obok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('specialists', function (Blueprint $table) {
            $table->string('external_specialist_id', 26)
                ->nullable()
                ->after('central_user_id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('specialists', function (Blueprint $table) {
            $table->dropColumn('external_specialist_id');
        });
    }
};
