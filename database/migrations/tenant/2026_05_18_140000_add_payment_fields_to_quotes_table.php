<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direct-charge payments MVP — patrz docs/TRANSPORT.md §13.
 *
 * Hovera NIE przyjmuje płatności. Klient płaci bezpośrednio do transportera
 * (Stripe payment link / Przelewy24 / BLIK / przelew tradycyjny). My tylko
 * wystawiamy UI affordance na quote landing.
 *
 *   - payment_url            link do bramki transportera (paste-and-go)
 *   - payment_method_label   krótki opis (np. "Stripe", "Przelewy24")
 *   - payment_completed_at   ręczne potwierdzenie przez transportera (no webhooks)
 *   - payment_notes          notatki wewnętrzne / dla klienta
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('payment_url', 2048)->nullable()->after('pdf_url');
            $table->string('payment_method_label', 80)->nullable()->after('payment_url');
            $table->timestamp('payment_completed_at')->nullable()->after('payment_method_label');
            $table->text('payment_notes')->nullable()->after('payment_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['payment_url', 'payment_method_label', 'payment_completed_at', 'payment_notes']);
        });
    }
};
