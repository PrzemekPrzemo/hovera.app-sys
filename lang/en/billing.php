<?php

declare(strict_types=1);

return [
    'navigation' => [
        'label' => 'hovera subscription',
    ],
    'page' => [
        'title' => 'hovera subscription',
        'subtitle' => 'Pick a plan for :stable. Recurring card payment — cancel any time.',
        'redirecting' => 'Redirecting to the billing page…',
        'click_here' => 'If the browser did not redirect automatically, click here.',
    ],
    'status' => [
        'active' => 'Subscription active',
        'trial_expired' => 'Trial expired — pick a plan',
        'trial_days_left' => '{1} :days day of trial left|[2,*] :days days of trial left',
    ],
    'period' => [
        'label' => 'Billing period',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly (-10%)',
        'month_short' => 'mo',
        'year_short' => 'yr',
    ],
    'actions' => [
        'choose' => 'Choose plan',
        'current' => 'Your current plan',
        'manage' => 'Manage subscription',
        'back_to_app' => 'Back to app',
    ],
    'manage' => [
        'title' => 'Manage subscription',
        'description' => 'Update card, download invoices or cancel from the Stripe portal.',
    ],
    'return' => [
        'title' => 'Subscription',
        'success_title' => 'Subscription active',
        'success_body' => 'Thanks! Your hovera subscription is active — receipt is on its way to your inbox.',
        'go_to_app' => 'Go to app',
        'pending_title' => 'Processing payment',
        'pending_body' => 'Stripe is confirming payment — this can take a few seconds. Refresh in a moment.',
        'refresh' => 'Refresh',
    ],
    'errors' => [
        'unknown_plan' => 'The selected plan does not exist or is inactive.',
        'checkout_failed' => 'Could not create the checkout session. Try again or contact support.',
        'portal_failed' => 'Could not open the billing portal. Please contact support.',
    ],
    'footer' => [
        'disclaimer' => 'Payments are processed by Stripe. Your card details are never stored on hovera servers. VAT invoices are generated automatically after each successful charge.',
    ],
];
