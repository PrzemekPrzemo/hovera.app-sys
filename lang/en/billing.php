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
        'vehicles_exceeded' => 'Limit of :limit vehicles in current plan — upgrade to add more.',
        'drivers_exceeded' => 'Limit of :limit drivers in current plan — upgrade to add more.',
    ],
    'onboarding_fee' => [
        'label' => 'Onboarding fee — :plan plan',
        'description' => 'One-time activation fee charged at the start of the subscription.',
    ],

    'payment_method' => [
        'label' => 'Payment method',
        'stripe' => 'Card — Stripe',
        'stripe_hint' => 'International cards, EUR/PLN, convenient self-service portal.',
        'payu' => 'PayU (card + BLIK + bank transfer)',
        'payu_hint' => 'Polish payment methods, lower fees, fast BLIK.',
    ],

    'payu' => [
        'card' => [
            'heading' => 'Your card (PayU)',
            'brand_mask' => ':brand :mask',
            'expires' => 'Expires: :expires',
            'no_expiry' => 'Expires: unknown',
            'cancel_cta' => 'Cancel recurring',
            'cancel_confirm' => 'Cancel for sure? You keep access until the end of the paid period, but after that the subscription will expire and you will need to pick a plan again.',
        ],
        'cancel_success' => 'Cancelled. Card removed — access until the end of the paid period, then the subscription expires.',
        'status' => [
            'past_due' => 'Last payment failed — check your card',
        ],
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
        'payu_charge_failed' => [
            'subject' => 'Subscription payment failed — :stable',
            'greeting' => 'Hi!',
            'intro' => 'We could not charge your recurring payment for the :plan subscription at :stable.',
            'attempt' => 'This is attempt #:attempts. We will try again in :next_in_days days.',
            'fix' => 'Common causes: insufficient funds, expired card, or a bank block. Please review your payment method in the billing panel to avoid your subscription being suspended.',
            'cta' => 'Review payment method',
            'signoff' => '— Hovera',
        ],
        'payu_subscription_suspended' => [
            'subject' => 'Subscription suspended — :stable',
            'greeting' => 'Hi!',
            'intro' => 'Unfortunately, after 3 attempts we could not charge your :plan subscription for :stable.',
            'consequence' => 'Your subscription has been suspended. Your data is safe, but access to premium features is paused until you start a new subscription.',
            'cta' => 'Resume subscription',
            'signoff' => '— Hovera',
        ],
    ],
];
