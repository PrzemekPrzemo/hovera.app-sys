<?php

declare(strict_types=1);

return [
    'section' => [
        'title' => 'PayU — auto-link on quotes',
        'description' => 'Auto-generate a PayU link (BLIK / bank transfer / card / Apple Pay / '
            .'Google Pay) for every new transport quote. Customer pays directly to your PayU account.',
        'disclaimer' => 'PayU is YOUR account, YOUR contract with PayU.pl S.A., YOUR invoices. '
            .'Hovera only technically forwards the customer to your checkout — all funds '
            .'land directly in your PayU account (Hovera is not a payment intermediary '
            .'for transport — see docs/TRANSPORT.md §12 and §16).',
    ],

    'form' => [
        'label' => [
            'autopay_enabled' => 'Auto-generate PayU link for new quotes',
        ],
        'helper' => [
            'autopay_enabled' => 'When enabled, creating a quote in PLN will automatically '
                .'register a PayU order and store the link as payment_url. The customer '
                .'will see a "Pay with PayU" button on the public quote page.',
            'credentials_pointer' => 'Configure pos_id / oauth_client_id / oauth_client_secret '
                .'/ md5_key in the "Payment settings" page (/app/payment-settings). One form '
                .'covers all PayU integrations.',
        ],
    ],

    'notify' => [
        'autopay_failed' => 'Could not generate the PayU link',
    ],

    'return' => [
        'paid' => 'Payment for quote {number} has been received — thank you!',
        'pending' => 'Payment for quote {number} is being verified. Please refresh '
            .'in a moment.',
        'unknown' => 'Quote not found. Please contact the carrier.',
    ],
];
