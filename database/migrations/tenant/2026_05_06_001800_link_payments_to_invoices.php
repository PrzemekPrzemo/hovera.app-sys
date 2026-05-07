<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Direct FK do faktury — zamiast trzymania w metadata.
            // Gdy klient płaci za fakturę przez signed URL, link tu wskazuje
            // co opłacamy. Webhook providera (Stripe/Mollie/...) z status
            // succeeded sprawdza tę kolumnę i marks invoice.paid_at.
            $table->string('invoice_id', 26)->nullable()->after('pass_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('invoice_id');
        });
    }
};
