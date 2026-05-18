<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache obliczonych tras dla wszystkich tenantów (central). Patrz docs/TRANSPORT.md §7.4.
 *
 * Cache_key = sha1(provider + profile + from_lat,from_lng + to_lat,to_lng).
 * TTL 30 dni (kontrolowane przez expires_at + nightly cleanup w cron'ie).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('route_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key', 64)->unique();   // sha1 hex = 40 chars, zostawiamy zapas

            $table->string('provider_id', 16);            // 'ors' / 'mapbox' / 'google'
            $table->string('profile', 16);                // 'truck' / 'car' / 'fast'

            $table->decimal('from_lat', 10, 7);
            $table->decimal('from_lng', 10, 7);
            $table->decimal('to_lat', 10, 7);
            $table->decimal('to_lng', 10, 7);

            $table->decimal('distance_km', 8, 2);
            $table->unsignedInteger('duration_seconds');
            $table->text('polyline')->nullable();

            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('route_cache');
    }
};
