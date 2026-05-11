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
        'one_time' => 'One-time',
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
    'suggested_badge' => 'Recommended',
    'trial_banner' => [
        'expires_today' => 'Your trial ends today.',
        'expires_tomorrow' => 'Trial ends tomorrow.',
        'days_left' => '{1} :days day of trial left.|[2,*] :days days of trial left.',
        'pro_pitch' => 'You have full Pro features, but the trial caps you at :horses horses and :clients clients. Pick Pro to lift the cap.',
        'cta_pro' => 'Pick Pro',
    ],
    'limits' => [
        'title' => 'Plan limit reached',
        'horses_exceeded' => 'Trial: :limit horses limit — pick a plan to add more.',
        'clients_exceeded' => 'Trial: :limit clients limit — pick a plan to add more.',
    ],
    'onboarding_fee' => [
        'label' => 'Onboarding fee — :plan plan',
        'description' => 'One-time activation fee charged at the start of the subscription.',
    ],
    'onboarding_fee_label' => 'one-time (onboarding fee)',
    'vat_notice' => 'Prices are net. 23% VAT is added to every amount.',
    'vat_notice_short' => '+ 23% VAT',
    'email' => [
        'invoice_paid' => [
            'subject' => 'Invoice :number — paid, thank you!',
            'heading' => 'Invoice :number paid',
            'intro' => 'Thanks! We received payment for your hovera subscription for :stable.',
            'field_number' => 'Invoice number',
            'field_plan' => 'Plan',
            'field_period' => 'Period',
            'field_total' => 'Gross amount',
            'field_paid_at' => 'Paid at',
            'pdf_pending' => 'The PDF invoice will appear in your billing panel shortly. We will also push it to KSeF (if configured).',
            'cta_billing' => 'Open billing panel',
            'thanks' => 'Glad to have you with us!',
            'signoff' => 'Best regards,',
        ],
    ],
];
