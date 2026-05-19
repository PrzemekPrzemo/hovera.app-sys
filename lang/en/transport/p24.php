<?php

declare(strict_types=1);

return [
    'section' => [
        'title' => 'Przelewy24 — auto-link on quotes',
        'description' => 'Auto-generate a P24 link (BLIK / bank transfer / card) for every '
            .'new transport quote. Customer pays directly to your P24 account.',
        'disclaimer' => 'Przelewy24 is YOUR account, YOUR contract with DialCom24, YOUR invoices. '
            .'Hovera only technically forwards the customer to your checkout — all funds '
            .'land directly in your P24 account (Hovera is not a payment intermediary '
            .'for transport — see docs/TRANSPORT.md §12 and §15.5).',
    ],

    'form' => [
        'label' => [
            'autopay_enabled' => 'Auto-generate P24 link for new quotes',
        ],
        'helper' => [
            'autopay_enabled' => 'When enabled, creating a quote in PLN will automatically '
                .'register a P24 session and store the link as payment_url. The customer '
                .'will see a "Pay with Przelewy24" button on the public quote page.',
            'credentials_pointer' => 'Configure merchant_id / pos_id / crc / api_key in '
                .'the "Payment settings" page (/app/payment-settings). One form covers all '
                .'P24 integrations (bookings, quotes).',
        ],
    ],

    'notify' => [
        'autopay_failed' => 'Could not generate the Przelewy24 link',
    ],

    'return' => [
        'paid' => 'Payment for quote {number} has been received — thank you!',
        'pending' => 'Payment for quote {number} is being verified. Please refresh '
            .'in a moment.',
        'unknown' => 'Quote not found. Please contact the carrier.',
    ],
];
