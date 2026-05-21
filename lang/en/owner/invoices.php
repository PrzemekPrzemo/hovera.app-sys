<?php

declare(strict_types=1);

return [
    'autobilling' => [
        'line' => [
            'box' => 'Box rental :box — :horse',
        ],
    ],

    'navigation' => 'Invoices',
    'model' => [
        'singular' => 'Invoice',
        'plural' => 'Invoices',
    ],

    'list' => [
        'title' => 'Invoices from your stables',
        'description' => 'Invoices issued by the stables boarding your horses. Drafts are hidden until issued.',
        'description_filtered' => 'Invoices related to :horse (line items with matching horse_id).',
        'empty_heading' => 'No invoices yet',
        'empty_description' => 'When your stable issues the first invoice for boarding, it will appear here.',
        'filter' => [
            'label' => 'Filter by horse:',
            'all' => 'All',
        ],
        'total_year' => 'Total for :year',
        'total_all' => 'Total (all years)',
        'year_all' => 'All years',
        'export_csv' => 'Export CSV',
    ],

    'show' => [
        'title' => 'Invoice :number',
        'title_draft' => 'Invoice (draft)',
        'back_to_list' => 'Back to list',
    ],

    'table' => [
        'number' => 'Number',
        'stable' => 'Stable',
        'kind' => 'Kind',
        'status' => 'Status',
        'issued_at' => 'Issued at',
        'due_at' => 'Due date',
        'period' => 'Period',
        'horse' => 'Horse',
        'total' => 'Total',
        'actions' => 'Actions',
        'view' => 'View',
    ],

    'section' => [
        'meta' => 'Invoice details',
        'seller' => 'Seller',
        'buyer' => 'Buyer',
        'items' => 'Line items',
        'totals' => 'Totals',
        'notes' => 'Notes',
    ],

    'field' => [
        'number' => 'Number',
        'kind' => 'Kind',
        'status' => 'Status',
        'issued_at' => 'Issued at',
        'sale_date' => 'Sale date',
        'due_at' => 'Due date',
        'paid_at' => 'Paid at',
        'period' => 'Billing period',
        'nip' => 'Tax ID',
        'address' => 'Address',
        'subtotal' => 'Net total',
        'vat' => 'VAT total',
        'total' => 'Gross total',
    ],

    'item' => [
        'position' => '#',
        'name' => 'Name',
        'quantity' => 'Qty',
        'unit_price' => 'Net unit price',
        'vat_rate' => 'VAT',
        'net' => 'Net',
        'total' => 'Gross',
    ],

    'action' => [
        'download_pdf' => 'Download PDF',
        'download_pdf_unavailable' => 'PDF coming soon',
        'pay_online' => 'Pay online',
        'pay_online_unavailable' => 'Online payment coming soon',
    ],

    'api' => [
        'pdf_not_implemented' => 'PDF generation will be available in a future iteration.',
        'pay_not_implemented' => 'Online payment will be available in a future iteration.',
    ],
];
