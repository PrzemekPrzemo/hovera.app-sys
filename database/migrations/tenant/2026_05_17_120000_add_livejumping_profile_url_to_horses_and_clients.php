<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LiveJumping.com integration — operator stajni wkleja URL profilu
 * konia i jeźdźca na livejumping.com w karcie. Hovera używa tego
 * URL-a żeby pobrać palmares i nadchodzące starty przez partnerski
 * API. URL trzymany jako nullable string (max 500) — działa tylko
 * gdy master admin włączy integrację w /admin/live-jumping-settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horses', function (Blueprint $table) {
            $table->string('livejumping_profile_url', 500)->nullable()->after('ueln');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('livejumping_profile_url', 500)->nullable()->after('pesel');
        });
    }

    public function down(): void
    {
        Schema::table('horses', function (Blueprint $table) {
            $table->dropColumn('livejumping_profile_url');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('livejumping_profile_url');
        });
    }
};
