<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arenas', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 120);
            $table->enum('type', ['indoor', 'outdoor', 'paddock', 'lunge', 'field'])
                ->default('indoor')
                ->index();
            $table->string('color', 7)->nullable();    // hex for calendar tinting
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arenas');
    }
};
