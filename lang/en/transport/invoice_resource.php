<?php

declare(strict_types=1);

return [
    'navigation' => 'Invoices',

    'section' => [
        'header' => 'Header',
        'parties' => 'Parties',
        'amounts' => 'Amounts',
        'dates' => 'Dates',
        'route' => 'Route',
        'notes' => 'Notes',
    ],

    'form' => [
        'label' => [
            'seller' => 'Seller',
            'buyer' => 'Buyer',
            'net_total' => 'Net',
            'vat_total' => 'VAT',
            'gross_total' => 'Gross',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Number',
            'kind' => 'Kind',
            'buyer' => 'Buyer',
            'issued_at' => 'Issued',
            'due_at' => 'Due',
            'total' => 'Gross',
            'status' => 'Status',
        ],
    ],

    'action' => [
        'download_pdf' => 'Download PDF',
        'send_email' => 'Send by email',
        'mark_paid' => 'Mark paid',
    ],

    'notify' => [
        'sent' => 'Invoice sent',
        'sent_body' => 'Invoice :number sent to :email with PDF attached.',
        'no_buyer_email' => 'Buyer has no email — download PDF and send manually.',
        'email_failed' => 'Send failed',
        'marked_paid' => 'Invoice marked as paid',
    ],
];
