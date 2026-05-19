<?php

declare(strict_types=1);

return [
    'payment_method_label' => 'Stripe (card / BLIK / Przelewy24)',

    'section' => [
        'title' => 'Stripe Connect Express (online payments)',
        'description' => 'One-click activation — your own Stripe Express account, money goes directly to you. Pay-online for every quote, automatically.',
        'disclaimer' => 'Stripe Connect Express: YOUR Stripe account, YOUR agreement with Stripe (KYC at Stripe). Hovera only technically enables checkout — money goes directly to you. Hovera may (but does not by default) charge a transaction commission — see §15 of marketplace terms.',
    ],

    'form' => [
        'label' => [
            'status' => 'Integration status',
        ],
    ],

    'status' => [
        'none' => 'Not connected',
        'pending' => 'Verification in progress at Stripe',
        'enabled' => 'Active — you can accept payments',
        'restricted' => 'Restricted — complete data at Stripe',
        'rejected' => 'Rejected — contact Stripe support',
    ],

    'action' => [
        'connect' => 'Connect Stripe account',
        'refresh_status' => 'Check status',
        'open_dashboard' => 'Open Stripe dashboard',
        'admin_sync' => 'Sync Stripe status',
    ],

    'notify' => [
        'onboard_failed' => 'Could not start Stripe onboarding.',
        'status_sync_failed' => 'Could not sync Stripe status.',
        'dashboard_failed' => 'Could not generate Stripe dashboard link.',
        'refreshed' => 'Stripe status refreshed.',
        'status_none' => 'No Stripe account — click "Connect Stripe account".',
        'status_pending' => 'KYC in progress — Stripe is verifying business data. Try again shortly.',
        'status_enabled' => 'Stripe account active — you can issue quotes with online payment.',
        'status_restricted' => 'Stripe restricted the account — check dashboard and complete missing data.',
        'status_rejected' => 'Stripe rejected the account — contact Stripe support required.',
    ],
];
