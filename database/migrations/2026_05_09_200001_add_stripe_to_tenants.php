<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-level Stripe identifiers — pinned 1:1 to a Stripe Customer
 * and (when active) one Subscription. We also denormalise the period
 * end so the trial banner / "subscription expired" middleware can
 * decide without round-tripping to Stripe on every request.
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
            $table->string('stripe_customer_id')->nullable()->unique()->after('plan_id');
            $table->string('stripe_subscription_id')->nullable()->unique()->after('stripe_customer_id');
            $table->timestamp('current_period_ends_at')->nullable()->after('trial_ends_at');
            $table->timestamp('subscription_ends_at')->nullable()->after('current_period_ends_at');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->dropUnique(['stripe_customer_id']);
            $table->dropUnique(['stripe_subscription_id']);
            $table->dropColumn([
                'stripe_customer_id',
                'stripe_subscription_id',
                'current_period_ends_at',
                'subscription_ends_at',
            ]);
        });
    }
};
