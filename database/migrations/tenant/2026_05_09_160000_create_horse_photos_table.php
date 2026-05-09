<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horse_photos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('horse_id')->constrained('horses')->cascadeOnDelete();

            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->string('mime', 120);
            $table->unsignedBigInteger('size_bytes');

            $table->string('caption', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->string('uploaded_by_role', 16)->default('stable'); // stable / client
            $table->string('uploaded_by_user_id', 26)->nullable();
            $table->string('uploaded_by_client_id', 26)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['horse_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horse_photos');
    }
};
