<?php

declare(strict_types=1);

return [
    'navigation' => 'JPK_FA(3) export',
    'title' => 'JPK_FA(3) export for a tenant',

    'form' => [
        'section' => 'Export parameters',
        'description' => 'Pick a tenant, year and optionally a quarter. JPK_FA(3) contains all issued (non-draft, non-void) VAT invoices for the selected period.',
        'tenant' => 'Tenant (stable / transporter)',
        'year' => 'Year',
        'quarter' => 'Quarter',
        'quarter_full_year' => 'Full year',
        'quarter_helper' => 'Leave empty to export the full year.',
    ],

    'action' => [
        'download' => 'Download JPK_FA(3) XML',
    ],

    'notify' => [
        'tenant_missing' => 'Tenant not found — pick from the list.',
        'failed' => 'JPK generation failed',
    ],
];
