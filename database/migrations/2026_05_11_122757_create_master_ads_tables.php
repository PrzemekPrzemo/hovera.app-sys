<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master ads — komunikaty/reklamy publikowane przez master admina,
 * targetowane na konkretną grupę użytkowników w panelach (`/app`).
 *
 * Targeting (JSON):
 *   - tenant_ids: [ulid, …]    — konkretne stajnie (puste = wszystkie)
 *   - roles:      [string, …]  — owner|admin|manager|instructor|employee|vet|viewer
 *   - countries:  [string, …]  — kody ISO PL|DE|FR|… (z tenant.country)
 *   - locales:    [string, …]  — pl|en|fr|de|ru (z user.locale)
 *   - user_ids:   [ulid, …]    — konkretni użytkownicy (override innych pól)
 *
 * Logika: pusta lista oznacza "no filter" (matchuje wszystkich).
 * Niepusta lista = AND-restriction. user_ids gdy non-empty matchuje tylko
 * tych użytkowników (pozostałe targeting fields ignorowane).
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::connection('central')->create('master_ads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title', 200);
            $table->text('body');
            $table->string('cta_label', 80)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('placement', 32)->default('banner'); // banner|modal
            $table->string('variant', 32)->default('info');     // info|promo|warning
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('targeting');
            $table->unsignedBigInteger('impressions_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::connection('central')->create('master_ad_dismissals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ad_id')->constrained('master_ads')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('dismissed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['ad_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('master_ad_dismissals');
        Schema::connection('central')->dropIfExists('master_ads');
    }
};
