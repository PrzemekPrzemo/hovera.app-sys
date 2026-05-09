<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('name', 120)->index();
            $table->enum('type', [
                'vaccination', 'deworming', 'vet_visit',
                'farrier', 'dentist', 'check_up',
                'medication', 'other',
            ])->index();

            // Days between visits. NULL = one-off (no follow-up suggested).
            $table->unsignedSmallInteger('interval_days')->nullable();

            $table->string('default_summary', 255)->nullable();
            $table->text('default_notes')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0)->index();

            $table->timestamps();
        });

        // Seed defaults: PL veterinary + farrier standards. Every new
        // tenant starts with these; owner can edit / disable / add custom.
        $now = now();
        $defaults = [
            ['Szczepienie tężec/grypa', 'vaccination', 365, 'Standardowe roczne szczepienie', 'Dawka przypominająca tężec + grypa końska wg schematu polskich klubów jeździeckich.', 1],
            ['Szczepienie EHV', 'vaccination', 180, 'Półroczne szczepienie EHV', 'Wirus opryszczki koni (EHV-1/4). Wymagane przez PZJ przed startami.', 2],
            ['Odrobaczanie', 'deworming', 90, 'Kwartalne odrobaczanie', 'Standardowy schemat: rotacja preparatów (iwermektyna / fenbendazol / prazikwantel).', 3],
            ['Kucie/korekcja', 'farrier', 42, 'Standardowa wizyta kowala', 'Co 6 tygodni — korekcja kopyt + wymiana podków przednich; tylne wg potrzeby.', 4],
            ['Przegląd zębów', 'dentist', 365, 'Roczna kontrola dentystyczna', 'Wyrównanie haczyków, kontrola zgryzu. Częściej u koni starszych (co 6 mies.).', 5],
            ['Kontrola weterynaryjna', 'check_up', 180, 'Półroczny przegląd', 'Ogólny przegląd kondycji, palpacja, kontrola serca i płuc.', 6],
        ];

        foreach ($defaults as [$name, $type, $interval, $summary, $notes, $sort]) {
            DB::table('treatment_templates')->insert([
                'id' => (string) Str::ulid(),
                'name' => $name,
                'type' => $type,
                'interval_days' => $interval,
                'default_summary' => $summary,
                'default_notes' => $notes,
                'is_active' => true,
                'sort_order' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_templates');
    }
};
