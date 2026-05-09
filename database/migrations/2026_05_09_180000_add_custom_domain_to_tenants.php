<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            // Vanity domain (e.g. "mojastajnia.pl") that maps to this
            // tenant's public micro-site at /s/{slug}/*. Available only
            // on plans that have the `vanity_domain` feature flag.
            //
            // Verification gate: visitors are routed only after we've
            // observed a TXT record matching `verified_at` is set in
            // the admin panel (TenantSettings UI handles the manual flow).
            $table->string('custom_domain', 255)->nullable()->unique()->after('settings');
            $table->timestamp('custom_domain_verified_at')->nullable()->after('custom_domain');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->dropColumn(['custom_domain', 'custom_domain_verified_at']);
        });
    }
};
