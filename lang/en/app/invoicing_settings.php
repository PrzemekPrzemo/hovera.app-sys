<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'numbering' => 'Invoice numbering',
            'numbering_description' => 'Placeholders: {seq}, {seq:NN} (e.g. {seq:4} → 0001), {YYYY}, {YY}, {MM}, {M}, {DD}, {prefix}.',
            'seller' => 'Seller details (snapshot on invoices)',
            'seller_description' => 'These details are stored on every new invoice when it is created. Editing stable details will not change previously issued invoices.',
        ],
        'label' => [
            'template_fv' => 'VAT invoice pattern',
            'template_pro' => 'Proforma pattern',
            'template_kor' => 'Correction pattern',
            'prefix' => 'Prefix (placeholder {prefix})',
            'prefix_placeholder' => 'e.g. STW',
            'reset_interval' => 'Numbering reset',
            'default_due_days' => 'Default due in days',
            'seller_name' => 'Seller name',
            'seller_nip' => 'Seller tax ID',
            'seller_address' => 'Address',
            'seller_postal_code' => 'Postal code',
            'seller_city' => 'City',
        ],
    ],

    'action' => [
        'saved' => 'Invoicing settings saved',
    ],
];
