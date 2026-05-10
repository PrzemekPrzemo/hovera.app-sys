<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trial 2.0 — pełny plan Pro na 30 dni z hard-cap na konie i klientów.
 * Override żyje na tenants, nie na plans, bo trial może być różny per
 * stajnia (np. ekstra wydłużony przez handel).
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('trial_max_horses')->nullable()->after('trial_ends_at');
            $table->unsignedInteger('trial_max_clients')->nullable()->after('trial_max_horses');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->dropColumn(['trial_max_horses', 'trial_max_clients']);
        });
    }
};
