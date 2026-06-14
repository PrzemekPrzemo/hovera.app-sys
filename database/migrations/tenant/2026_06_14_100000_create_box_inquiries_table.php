<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Publiczne zapytania o rezerwację boksu — przychodzą z `/s/{slug}/box-inquiry`
 * (przez embed widget lub bezpośrednio z public micro-site). Stable widzi
 * listę w panelu i może oznaczyć jako "contacted/closed", odpowiedzieć
 * mailem lub założyć klienta + rezerwację boksu.
 *
 * Bez auth — public POST. Throttle 5/h per IP (BoxInquiryController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('box_inquiries', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            // Kontakt
            $table->string('name', 160);
            $table->string('email', 255);
            $table->string('phone', 40)->nullable();

            // Detale zapytania
            $table->unsignedSmallInteger('horse_count')->default(1);
            $table->date('preferred_from')->nullable();
            $table->text('message')->nullable();

            // Lifecycle
            $table->string('status', 16)->default('new')->index();
            $table->timestamp('responded_at')->nullable();
            $table->string('responded_by_user_id', 26)->nullable();
            $table->text('response_notes')->nullable();

            // Anti-abuse / audit
            $table->string('source', 32)->default('public_site');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('box_inquiries');
    }
};
