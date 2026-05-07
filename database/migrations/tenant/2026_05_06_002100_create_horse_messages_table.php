<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Threaded messaging stajnia ↔ właściciel konia.
 *
 * Każda wiadomość jest powiązana z konkretnym koniem — to upraszcza UX
 * dla obu stron (właściciel widzi w panelu konia "rozmowy ze stajnią o
 * tym koniu", stajnia w karcie konia ma tab "Wiadomości"). Brak globalnej
 * skrzynki "wszystkie wiadomości od klienta" — wszystko jest w kontekście
 * konia.
 *
 * Attachments — JSON column ze ścieżkami do plików w storage. Pliki są
 * uploadowane lokalnie (dysk `local`); w przyszłości łatwo przejść na
 * S3 zmieniając konfigurację dysku w controllerze.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horse_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('horse_id')->constrained('horses')->cascadeOnDelete();

            // Kierunek + uczestnicy
            //   from_stable: nadawca to stajnia (sender_user_id z central.users),
            //                odbiorca to właściciel konia (client_id)
            //   from_client: nadawca to właściciel (client_id),
            //                odbiorca to wszyscy owner/admin stajni (broadcast)
            $table->enum('direction', ['from_stable', 'from_client'])->index();
            $table->string('sender_user_id', 26)->nullable();   // central user id (gdy from_stable)
            $table->foreignUlid('client_id')->constrained('clients')->cascadeOnDelete();

            $table->string('subject', 200)->nullable();
            $table->text('body');

            // Lista attachmentów (JSON: [{path, original_name, mime, size}, ...])
            $table->json('attachments')->nullable();

            $table->timestamp('sent_at')->index();
            // Flaga "odczytane" per kierunek — gdy from_stable=true, klient
            // odczyta → ustawimy read_by_client_at; gdy from_client=true,
            // stajnia → read_by_stable_at.
            $table->timestamp('read_by_client_at')->nullable();
            $table->timestamp('read_by_stable_at')->nullable();

            $table->timestamps();

            $table->index(['horse_id', 'sent_at']);
            $table->index(['client_id', 'read_by_client_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horse_messages');
    }
};
