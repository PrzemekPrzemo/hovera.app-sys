<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discriminator klienta zlecenia transportowego — patrz
 * docs/MARKETPLACE-ROADMAP.md PR 7.
 *
 * Tryby:
 *   anonymous   — lead z publicznego formularza bez logowania (defaultowy)
 *   stable      — stable jest klientem (FV do stajni)
 *   owner       — boarder jest klientem (FV do owner'a, nie stajni;
 *                 client_user_id wskazuje na owner.user_id z central)
 *   transporter — lead z panelu transportera (np. sub-zlecenie)
 *
 * Powiązane pola:
 *   - client_user_id        — gdy client_type='owner' (FK do central.users)
 *   - created_by_tenant_id  — gdy lead z panelu stable (audit + back-link
 *                              do "Moje wystawione leady" w panelu stable)
 *
 * Default = 'anonymous' zachowuje stary behaviour dla istniejących leadów.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('transport_leads', function (Blueprint $table) {
            $table->enum('client_type', ['anonymous', 'stable', 'owner', 'transporter'])
                ->default('anonymous')
                ->after('originator_phone')
                ->index();

            $table->ulid('client_user_id')->nullable()->after('client_type')->index();
            $table->ulid('created_by_tenant_id')->nullable()->after('client_user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('transport_leads', function (Blueprint $table) {
            $table->dropIndex(['client_type']);
            $table->dropIndex(['client_user_id']);
            $table->dropIndex(['created_by_tenant_id']);
            $table->dropColumn(['client_type', 'client_user_id', 'created_by_tenant_id']);
        });
    }
};
