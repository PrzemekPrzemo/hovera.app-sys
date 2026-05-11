<?php

declare(strict_types=1);

return [
    'navigation' => 'SaaS invoice numbering',
    'title' => 'hovera invoice numbering and templates',

    'section' => [
        'numbering' => 'Numbering',
        'numbering_help' => 'Number template used to generate consecutive SaaS invoices (hovera → stable). Tokens: {YYYY} 4-digit year, {YY} 2-digit year, {MM} 2-digit month, {NNNN} zero-padded 4-digit sequence, {NN} 2-digit sequence, {SEQ} sequence without padding.',
        'defaults' => 'Invoice defaults',
        'text' => 'Standard text fields',
        'text_help' => 'Text inserted into every issued invoice — payment terms, footer with bank account number, contact info.',
    ],

    'field' => [
        'number_template' => 'Numbering template',
        'number_template_help' => 'Example: HVR/{YYYY}/{MM}/{NNNN} → HVR/2026/05/0042',
        'reset_cycle' => 'Sequence reset cycle',
        'next_sequence' => 'Next number (override)',
        'next_sequence_placeholder' => 'leave empty to continue',
        'next_sequence_help' => 'If you enter e.g. 100 — the next issued invoice will use sequence 100 (then 101, 102…). Useful after importing from another system.',
        'currency' => 'Currency',
        'vat_rate' => 'VAT rate',
        'due_days' => 'Payment due',
        'due_days_suffix' => 'days',
        'payment_terms' => 'Payment terms',
        'payment_terms_placeholder' => 'e.g. "Payable within 14 days of issue date. Account: ..."',
        'footer_note' => 'Invoice footer',
        'footer_note_help' => 'Printed at the bottom of every invoice PDF + written to the KSeF XML as an optional field.',
        'footer_note_placeholder' => 'e.g. "Thank you for your business! Questions? support@hovera.app"',
    ],

    'cycle' => [
        'monthly' => 'Monthly (reset on 1st of month)',
        'yearly' => 'Yearly (reset on January 1st)',
        'never' => 'Never (continuous sequence)',
    ],

    'action' => [
        'save_button' => 'Save configuration',
        'saved' => 'Numbering configuration saved.',
    ],
];
