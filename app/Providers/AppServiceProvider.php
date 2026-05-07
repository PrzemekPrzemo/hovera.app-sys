<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Tenant\Payment;
use App\Observers\PaymentObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Payment observer: gdy provider webhook ustawi status=succeeded,
        // automatycznie marks linked Invoice jako paid.
        Payment::observe(PaymentObserver::class);
    }
}
