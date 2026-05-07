<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repozytorium dokumentów per koń. Stajnia trzyma tu paszport, umowy
 * pensjonatu, polisy, książkę szczepień. Właściciel widzi wszystko +
 * może dorzucić własne (np. własna polisa, kontrakt sprzedaży).
 *
 * Pliki w storage/app/horse-documents/{tenant_id}/{horse_id}/{ulid}_{slug}.ext
 * (analogicznie do horse_messages — ten sam dysk local + private).
 *
 * uploaded_by_role: rozróżnienie kto wgrał — stajnia może edytować
 * własne wpisy + wpisy klienta; klient widzi wszystko, ale może
 * usuwać tylko swoje.
 *
 * valid_from / valid_until — dla dokumentów które mają ważność
 * (polisy, badania, licencje) — później widget alertów "polisa
 * wygasa za 14 dni".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horse_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('horse_id')->constrained('horses')->cascadeOnDelete();

            $table->string('name', 200);
            $table->string('kind', 32)->index();    // HorseDocumentKind
            $table->string('description', 500)->nullable();

            // Storage
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->string('mime', 120);
            $table->unsignedBigInteger('size_bytes');

            // Audit pochodzenia
            $table->enum('uploaded_by_role', ['stable', 'client'])->index();
            $table->string('uploaded_by_user_id', 26)->nullable();    // central (gdy stable)
            $table->string('uploaded_by_client_id', 26)->nullable();  // tenant (gdy client)

            // Ważność (dla polis / badań / licencji)
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable()->index();

            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['horse_id', 'kind']);
            $table->index(['horse_id', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horse_documents');
    }
};
