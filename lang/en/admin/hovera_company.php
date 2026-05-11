<?php

declare(strict_types=1);

return [
    'navigation' => 'Company details',
    'title' => 'hovera company details (invoice issuer)',

    'section' => [
        'identity' => 'Identity',
        'identity_help' => 'Used as seller details on SaaS invoices issued to stables (KSeF, PDF, FA(3) XML).',
        'address' => 'Registered address',
        'contact' => 'Contact',
        'bank' => 'Bank account',
        'bank_help' => 'IBAN is placed on invoices as the wire transfer destination (when a customer pays via traditional bank transfer instead of Stripe/P24).',
    ],

    'field' => [
        'name' => 'Company name',
        'legal_form' => 'Legal form',
        'nip' => 'NIP (PL tax ID)',
        'regon' => 'REGON',
        'krs' => 'KRS (registry number)',
        'court' => 'Registry court',
        'capital' => 'Share capital',
        'street' => 'Street and number',
        'postal_code' => 'Postal code',
        'city' => 'City',
        'country' => 'Country (ISO code)',
        'email' => 'Email',
        'phone' => 'Phone',
        'bank_name' => 'Bank name',
        'iban' => 'IBAN',
        'swift' => 'SWIFT/BIC',
    ],

    'action' => [
        'save_button' => 'Save company details',
        'saved' => 'Company details saved.',
    ],
];
