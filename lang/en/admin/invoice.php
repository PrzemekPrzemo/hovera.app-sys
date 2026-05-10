<?php

declare(strict_types=1);

return [
    'navigation' => 'SaaS Invoices',
    'model' => 'SaaS Invoice',
    'model_plural' => 'SaaS Invoices',

    'kind' => [
        'regular' => 'Regular (FV)',
        'proforma' => 'Proforma',
        'correction' => 'Correction',
    ],

    'form' => [
        'section' => [
            'basics' => 'Basics',
            'amounts' => 'Amounts',
            'dates' => 'Dates',
        ],
        'label' => [
            'tenant' => 'Stable (buyer)',
            'number' => 'Invoice number',
            'kind' => 'Type',
            'subtotal' => 'Net (cents)',
            'vat_rate' => 'VAT rate (%)',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Number',
            'tenant' => 'Stable',
            'issued_at' => 'Issued',
            'total' => 'Total',
            'status' => 'Status',
            'ksef_status' => 'KSeF',
        ],
    ],

    'action' => [
        'issue_manual' => 'Issue invoice manually',
        'send_p24_link' => 'Send P24 link',
        'p24_link_generated' => 'Przelewy24 link generated',
        'p24_link_failed' => 'Failed to generate P24 link',
        'send_to_ksef' => 'Send to KSeF',
        'ksef_sent' => 'Sent to KSeF',
        'ksef_failed' => 'KSeF send failed',
        'ksef_reference' => 'KSeF reference',
        'download_pdf' => 'Download PDF',
        'pdf_stub_title' => 'PDF generation deferred',
        'pdf_stub_body' => 'Full PDF invoice generation needs dompdf/snappy — coming in a follow-up PR.',
        'resend_email' => 'Resend e-mail',
    ],

    'p24_return' => [
        'paid' => 'Payment for invoice :number has been confirmed.',
        'pending' => 'Thanks! Payment for invoice :number is being verified — this usually takes a few minutes.',
        'unknown' => 'Invoice not recognised — check your confirmation email.',
    ],
];
