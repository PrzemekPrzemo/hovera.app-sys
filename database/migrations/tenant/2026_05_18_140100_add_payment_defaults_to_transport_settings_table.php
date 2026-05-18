<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domyślne ustawienia płatności — direct charge MVP. Patrz docs/TRANSPORT.md §13.
 *
 * Transporter ustawia raz w panelu; system auto-fill'uje quote.payment_url
 * z template'a podczas tworzenia (placeholdery: {quote_number}, {gross_total_pln},
 * {customer_name}). Jeśli żadnego URL'a nie ma, na landing'u wyświetlamy
 * payment_instructions jako fallback (np. dane do przelewu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            $table->string('default_payment_url_template', 2048)->nullable()->after('routing_provider');
            $table->string('default_payment_method_label', 80)->nullable()->after('default_payment_url_template');
            $table->text('payment_instructions')->nullable()->after('default_payment_method_label');
        });
    }

    public function down(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            $table->dropColumn(['default_payment_url_template', 'default_payment_method_label', 'payment_instructions']);
        });
    }
};
