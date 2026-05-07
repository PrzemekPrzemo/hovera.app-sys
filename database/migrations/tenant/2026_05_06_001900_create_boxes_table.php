<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boksy stajenne. Każda stajnia może wprowadzić swoje boksy z numerem,
 * typem, rozmiarem, miesięcznym kosztem pensjonatu. Konie są przypisane
 * przez `horses.box_id` (jeden koń → jeden box, jeden box → 0..N koni
 * jeśli capacity > 1, ale typowo 1).
 *
 * `box_assignments` — historia zmian (kto był w boxie kiedy). Pozwala
 * portalowi klienta pokazać "twój koń był w boxie A do 12.05.2026,
 * teraz jest w boxie B".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boxes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 60);                 // np. "B-12" / "Box przy wejściu"
            $table->string('label', 20)->nullable();    // krótki kod do wyświetlania, np. "12"
            $table->enum('type', ['indoor', 'paddock', 'outdoor', 'quarantine'])
                ->default('indoor')->index();
            $table->unsignedSmallInteger('size_m2')->nullable();
            $table->unsignedSmallInteger('capacity')->default(1);   // ile koni może być
            $table->unsignedInteger('monthly_rate_cents')->nullable(); // domyślna cena pensjonatu
            $table->boolean('is_active')->default(true);             // false = chowamy z UI (np. remont)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('horses', function (Blueprint $table) {
            $table->foreignUlid('box_id')->nullable()->after('owner_client_id')
                ->constrained('boxes')->nullOnDelete();
            $table->index('box_id');
        });

        Schema::create('box_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('horse_id')->constrained('horses')->cascadeOnDelete();
            $table->foreignUlid('box_id')->constrained('boxes')->cascadeOnDelete();
            $table->timestamp('assigned_at')->nullable()->index();
            $table->timestamp('vacated_at')->nullable()->index(); // null = wciąż tam jest
            $table->string('reason', 120)->nullable();             // np. "remont B-3"
            $table->string('assigned_by_user_id', 26)->nullable(); // kto przesunął
            $table->timestamps();

            // Per koń typowo 1 active assignment (vacated_at = null) — nie wymuszamy
            // unique przez DB constraint bo SQLite nie wspiera partial indexes
            // tak samo jak MySQL ułomnie. Sprawdzamy w aplikacji.
            $table->index(['horse_id', 'vacated_at']);
            $table->index(['box_id', 'vacated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('box_assignments');
        Schema::table('horses', function (Blueprint $table) {
            $table->dropForeign(['box_id']);
            $table->dropColumn('box_id');
        });
        Schema::dropIfExists('boxes');
    }
};
