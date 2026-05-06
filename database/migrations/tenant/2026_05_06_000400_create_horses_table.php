<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 120)->index();

            // Identification
            $table->string('microchip', 32)->nullable()->unique();
            $table->string('passport_number', 64)->nullable()->index();
            $table->string('ueln', 15)->nullable()->index();   // Universal Equine Life Number

            // Pedigree-light (full breeding records come later)
            $table->string('breed', 120)->nullable();
            $table->enum('sex', ['mare', 'stallion', 'gelding', 'filly', 'colt', 'foal'])->nullable();
            $table->string('color', 60)->nullable();
            $table->date('birth_date')->nullable();

            // Owner — soft FK to clients in this tenant DB. Nullable
            // because some horses are stable-owned (school horses).
            $table->foreignUlid('owner_client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();

            $table->string('cover_image_path')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horses');
    }
};
