<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stripe Connect Express — transporter direct-charge. Patrz docs/TRANSPORT.md §15.6.
 *
 * Każdy transporter ma WŁASNE Stripe Connect Express konto (KYC u Stripe,
 * pieniądze trafiają BEZPOŚREDNIO do niego — Hovera jest tylko platformą
 * facylitującą onboarding + checkout). Trzymamy id konta na poziomie
 * central (Tenant) bo:
 *   - webhook'i Stripe Connect lecą do publicznego endpointu (bez tenant
 *     context'u) — musimy mieć stripe_account_id → tenant_id lookup
 *   - master admin widzi status w panelu admin / overview
 *
 * Status (enum-string):
 *   - `none`       : transporter jeszcze nie zaczął onboardingu
 *   - `pending`    : konto utworzone, KYC w trakcie (charges_enabled=false)
 *   - `enabled`    : KYC OK, można przyjmować płatności (charges_enabled=true)
 *   - `restricted` : Stripe ograniczył konto (np. brak dokumentów / weryfikacji)
 *   - `rejected`   : Stripe odrzucił konto (kontakt z supportem)
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->string('stripe_connect_account_id', 50)
                ->nullable()
                ->after('stripe_subscription_id')
                ->index();
            $table->string('stripe_connect_status', 16)
                ->default('none')
                ->after('stripe_connect_account_id');
            $table->timestamp('stripe_connect_onboarded_at')
                ->nullable()
                ->after('stripe_connect_status');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->dropIndex(['stripe_connect_account_id']);
            $table->dropColumn([
                'stripe_connect_account_id',
                'stripe_connect_status',
                'stripe_connect_onboarded_at',
            ]);
        });
    }
};
