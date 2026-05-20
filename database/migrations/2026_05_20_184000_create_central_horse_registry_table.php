<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centralny rejestr koni — cross-tenant source of truth dla par
 * (owner_user, horse). Per docs/MARKETPLACE-ROADMAP.md PR 4/5.
 *
 * Tenant DB (`stable` + `owner`) ma własną tabelę `horses` — to są
 * lokalne projekcje konia (z box_id, owner_client_id, vet, etc.) +
 * soft FK do central rejestru przez `central_horse_id`. Dzięki
 * central rejestrowi:
 *   - Owner widzi gdzie jego koń aktualnie boarduje (horse_boarding_assignments)
 *   - Stable widzi pensjonariuszy z central'a (przy invite owner'a)
 *   - Stabilne ID koneczne dla cross-tenant operations (np. transfer
 *     boarding'u stajni A → stajni B)
 *
 * `passport_no` — unikalny jeśli wpisany (NULL OK, duplikaty NOT OK).
 * SQLite + MySQL traktują NULL'e w UNIQUE inaczej — w MySQL multiple
 * NULL OK natywnie, w SQLite też. Index zwykły.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('central_horse_registry', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // FK do central.users — owner, czyli user z `horse_owner` tenant'em.
            // NULL dopuszczone: stable może mieć horse'a bez przypisanego
            // owner account'a (school horse, własność stajni). Wtedy stable
            // claim'uje owner'a poprzez invite (PR 4/5 follow-up).
            $table->ulid('primary_owner_user_id')->nullable()->index();

            $table->string('name', 120);
            $table->string('breed', 120)->nullable();
            $table->date('dob')->nullable();
            $table->string('passport_no', 64)->nullable()->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('central_horse_registry');
    }
};
