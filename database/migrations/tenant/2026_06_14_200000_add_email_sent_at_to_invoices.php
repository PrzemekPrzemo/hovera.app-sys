<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency marker dla bulk-email faktury do klienta. Stable owner
 * klika "Wyślij maile" na liście FV (Filament bulk action) → job
 * iteruje wybrane, ale skipuje te z wypełnionym `email_sent_at` chyba
 * że flag "Wyślij ponownie" włączony.
 *
 * Reset: gdy operator chce ponownie wysłać (np. klient nie dostał),
 * używa bulk action "Wyślij ponownie" — zeruje `email_sent_at` przed
 * dispatch'em joba.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestamp('email_sent_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('email_sent_at');
        });
    }
};
