<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            // Long-lived bearer token in the public .ics feed URL.
            // Calendar apps poll the feed without auth, so this token IS
            // the credential — keep it long and never log it.
            $table->string('ics_token', 64)->nullable()->unique();
        });

        // Backfill tokens for existing instructors so feed URLs are
        // available immediately after migration.
        $existing = DB::table('instructors')->whereNull('ics_token')->pluck('id');
        foreach ($existing as $id) {
            DB::table('instructors')
                ->where('id', $id)
                ->update(['ics_token' => Str::random(48)]);
        }
    }

    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropColumn('ics_token');
        });
    }
};
