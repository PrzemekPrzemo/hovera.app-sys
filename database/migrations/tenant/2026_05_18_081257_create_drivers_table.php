<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kierowcy w bazie transportera. Patrz docs/TRANSPORT.md §3.2.
 *
 * Email + telefon używane przez dedykowany SMTP "transport" do
 * notyfikacji o przydzielonych zleceniach (LeadDispatcher w fazie 6).
 * Prawo jazdy + ADR + ważność — do filtrowania kto może wziąć dany kurs
 * (zwłaszcza międzynarodowe / transport materiałów niebezpiecznych).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Opcjonalny link do central User — pozwala kierowcy logować się
            // do hovery (lekka aplikacja "moja trasa", faza późniejsza).
            $table->string('central_user_id', 26)->nullable()->index();

            $table->string('first_name', 60);
            $table->string('last_name', 80);
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();

            // Prawo jazdy
            $table->string('license_number', 32)->nullable();
            $table->json('license_categories')->nullable();        // ["C", "C+E", "B"]
            $table->date('license_expires_at')->nullable();

            // ADR / uprawnienia dodatkowe — transport zwierząt nie wymaga
            // ADR, ale klienci często pytają o "świadectwo kwalifikacji
            // zawodowej" + szkolenie zwierząt (ustawa o transporcie zwierząt).
            $table->boolean('has_animal_transport_cert')->default(false);
            $table->date('animal_transport_cert_expires_at')->nullable();
            $table->boolean('has_adr')->default(false);
            $table->date('adr_expires_at')->nullable();

            $table->date('date_of_birth')->nullable();
            $table->date('hire_date')->nullable();

            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
