<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR O5 Channel B — central-level external specialist (vet, farrier,
 * groomer, dietetyk) z magic-link auth. NIE jest to per-stable contact
 * (tamto żyje w tenant DB jako `specialists` table) — `external_specialists`
 * jest cross-tenant identity: jeden vet może być zaproszony przez wiele
 * stajni i jednego owner'a do różnych koni, ale jest tym samym user'em
 * z perspektywy auth.
 *
 * Per captured decisions (docs/PHASE-1-DECISIONS-CAPTURED.md §3):
 *   - Hybrid invite: stable wpisuje email → magic link → vet ustawia
 *     hasło → unverified badge dopóki master-admin nie zweryfikuje
 *   - 7d magic link + password setup + email verification code
 *
 * Schema:
 *   - email (unique) — primary identity
 *   - display_name + specialty — UI labels
 *   - password_hash — set po setup'ie (nullable do tego czasu)
 *   - verified_at / verified_by_user_id — hovera-side verification
 *   - email_verified_at — confirmed email (separate od specialist verification)
 *   - created_by_user_id — kto pierwszy zaprosił (audit trail)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('external_specialists', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('email', 191)->unique();
            $table->string('display_name', 200);
            $table->string('specialty', 64)->nullable(); // 'vet', 'farrier', 'groomer', 'dietetyk', custom...
            $table->string('phone', 40)->nullable();
            $table->string('password_hash')->nullable();

            // Email verification — czy vet potwierdził że to jego adres
            // przez code w secondary email. Osobne od `verified_at`.
            $table->timestamp('email_verified_at')->nullable();

            // Hovera-side verification — master-admin sprawdza PWZ /
            // licencję manualnie. Bez tego thread'y pokazują 'unverified'
            // badge w UI (per decisions hybrid invite).
            $table->timestamp('verified_at')->nullable();
            $table->foreignUlid('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Kto zaprosił po raz pierwszy — audit.
            $table->foreignUlid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->json('metadata')->nullable(); // PWZ number, licencja, custom flags

            $table->timestamps();
            $table->softDeletes();

            $table->index('verified_at', 'external_specialists_verified_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('external_specialists');
    }
};
