<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumenty wymagane do weryfikacji konta transportera.
 *
 *   document_type     → enum (TransporterDocumentType): company_registration,
 *                       animal_transport_cert, insurance_ocp/ocs,
 *                       vehicle_registration, other
 *   status            → pending/verified/rejected (per pojedynczy dokument;
 *                       Tenant.verification_status agreguje stan)
 *   expires_at        → data ważności (dla certyfikatów/ubezpieczeń) lub NULL
 *   verified_by_user_id → master admin user id z central
 *   rejection_reason  → wymagane gdy status=rejected
 *
 * Per-tenant tabela — każdy transporter ma swoje dokumenty w swojej DB
 * (czyste oddzielenie danych prawnych firm).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transporter_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('document_type', 32)->index();
            $table->enum('status', ['pending', 'verified', 'rejected'])
                ->default('pending')
                ->index();

            // Pliki — używamy storage path (disk konfigurowalny w
            // transport.documents.disk), nie blob w DB.
            $table->string('file_path');
            $table->unsignedInteger('file_size')->nullable();      // bajty
            $table->string('file_mime', 96)->nullable();
            $table->string('original_filename')->nullable();

            $table->date('expires_at')->nullable();
            $table->date('issued_at')->nullable();

            $table->ulid('verified_by_user_id')->nullable();        // central User
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->text('notes')->nullable();                      // notatki transportera (np. "OC firmowe XYZ")

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transporter_documents');
    }
};
