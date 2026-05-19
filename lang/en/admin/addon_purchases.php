<?php

declare(strict_types=1);

return [
    'navigation' => 'Add-on purchases',
    'model' => 'Add-on purchase',
    'model_plural' => 'Add-on purchases',

    'form' => [
        'section' => [
            'basics' => 'Basic information',
            'status' => 'Status and payment',
        ],
        'label' => [
            'tenant' => 'Stable (tenant)',
            'addon' => 'Add-on (pick from catalog)',
            'addon_code' => 'Add-on code',
            'addon_name' => 'Add-on name (snapshot)',
            'currency' => 'Currency',
            'amount_cents' => 'Amount (minor units)',
            'status' => 'Status',
            'p24_link' => 'P24 link (after generation)',
            'p24_link_none' => '— no link, use the "Generate P24 link" action',
        ],
        'helper' => [
            'amount_cents' => 'Amount in the smallest unit (grosze for PLN, cents for EUR). '
                .'Auto-populated from PlanAddon pricing after you pick the add-on above.',
        ],
    ],

    'status' => [
        'pending' => 'Pending payment',
        'paid' => 'Paid',
        'failed' => 'Payment failed',
        'cancelled' => 'Cancelled',
    ],

    'table' => [
        'column' => [
            'tenant' => 'Stable',
            'addon' => 'Add-on',
            'amount' => 'Amount',
            'status' => 'Status',
            'paid_at' => 'Paid at',
            'created_at' => 'Created at',
        ],
    ],

    'action' => [
        'generate_p24_link' => 'Generate P24 link',
    ],

    'notify' => [
        'link_generated' => 'P24 link generated — copy below and send to the client',
        'link_failed' => 'Could not generate the P24 link',
    ],

    'return' => [
        'paid' => 'Add-on purchase "{code}" has been received — thank you!',
        'pending' => 'Add-on purchase "{code}" is being verified.',
        'unknown' => 'Add-on purchase not found.',
    ],
];
