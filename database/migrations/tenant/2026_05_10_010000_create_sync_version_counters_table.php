<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Single-row monotonic counter per tenant DB. Incremented atomically
// (UPDATE ... SET v=v+1) by HasSyncVersion trait on each save/delete.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_version_counters', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary(); // always 1
            $table->unsignedBigInteger('current_version')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        \DB::table('sync_version_counters')->insert([
            'id' => 1,
            'current_version' => 0,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_version_counters');
    }
};
