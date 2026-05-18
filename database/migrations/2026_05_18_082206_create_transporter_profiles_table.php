<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Publiczny profil transportera — podstawa pod stronę `/t/{slug}`. Patrz
 * docs/TRANSPORT.md §3.2 + §1.1 D5.
 *
 * 1:1 z tenantem typu transporter. Tworzony przy pierwszej edycji
 * profilu publicznego (lazy). Pole `slug` może odbiegać od `tenants.slug`
 * — np. tenant `kowalscy-transport` ale URL `/t/kowalscy-konie`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('transporter_profiles', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->unique();              // 1:1 z tenants
            $table->string('slug', 80)->unique();              // używany w /t/{slug}

            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();

            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_website')->nullable();

            $table->json('social_links')->nullable();          // {facebook, instagram, ...}
            $table->json('seo')->nullable();                   // {meta_title, meta_description, og_image}

            $table->boolean('is_published')->default(false)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('transporter_profiles');
    }
};
