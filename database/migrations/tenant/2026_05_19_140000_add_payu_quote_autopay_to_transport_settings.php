<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-transporter PayU quote autopay toggle — patrz docs/TRANSPORT.md §16.
 *
 * Analogiczne do `p24_quote_autopay_enabled`. Sam credential PayU
 * (pos_id, oauth_client_id, oauth_client_secret, md5_key, second_key)
 * żyje w `tenants.settings.payments.payu` (central, encrypted) — tu
 * tylko per-transport toggle żeby CreateQuote::afterCreate wiedział
 * czy generować PayU link czy nie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            $table->boolean('payu_quote_autopay_enabled')
                ->default(false)
                ->after('p24_quote_autopay_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('transport_settings', function (Blueprint $table) {
            $table->dropColumn('payu_quote_autopay_enabled');
        });
    }
};
