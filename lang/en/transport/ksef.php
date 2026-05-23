<?php

declare(strict_types=1);

return [
    'section' => [
        'title' => 'KSeF (Polish e-Invoicing)',
        'description' => 'KSeF integration for transport invoices — issued by you.',
        'disclaimer' => 'You get your KSeF token from your own KSeF account (mf.gov.pl). '
            .'Hovera only passes your invoices through — it is YOUR token, YOUR tax ID, '
            .'YOUR responsibility for accounting compliance. Hovera is not a party to your '
            .'transport contracts nor the issuer of your invoices (see docs/TRANSPORT.md §12).',
        'invoice_title' => 'KSeF — submission status',
        'invoice_description' => 'Information about submission to the Polish KSeF system (if enabled).',
    ],

    'form' => [
        'label' => [
            'nip' => 'Issuer tax ID (yours)',
            'environment' => 'KSeF environment',
            'token' => 'KSeF authorization token',
            'enabled' => 'Enable KSeF integration',
            'invoice_status' => 'KSeF status',
            'reference_number' => 'KSeF reference number',
            'submitted_at' => 'Submitted at',
        ],
        'helper' => [
            'nip' => '10-digit Polish tax ID used in KSeF. We pre-fill with the account tax ID.',
            'token_empty' => 'Paste the token generated in the Polish MF panel. '
                .'We store it encrypted.',
            'token_set' => 'Token is saved. Enter a new value to replace, leave blank to keep current.',
            'enabled' => 'When enabled, a "Send to KSeF" action appears on invoices. '
                .'Cannot be enabled without a token.',
        ],
        'option' => [
            'environment' => [
                'test' => 'Test (ksef-test.mf.gov.pl)',
                'demo' => 'Demo (ksef-demo.mf.gov.pl)',
                'production' => 'Production (ksef.mf.gov.pl)',
            ],
        ],
    ],

    'action' => [
        'submit' => 'Send to KSeF',
        'submit_tooltip' => 'Requires a handshake with the Ministry of Finance (challenge + encryption). '
            .'The first submission after a longer break takes a couple of seconds — '
            .'subsequent ones within 2h reuse the cached session.',
        'submit_confirm' => 'Submit this invoice to KSeF? This cannot be undone.',
        'submit_bulk' => 'Send selected to KSeF',
        'submit_bulk_confirm' => 'Submit selected invoices (max 50) to KSeF? Irreversible.',
        'refresh' => 'Refresh KSeF status',
        'test_connection' => 'Test KSeF connection',
    ],

    'notify' => [
        'submitted' => 'Invoice submitted to KSeF.',
        'submit_failed' => 'Failed to submit to KSeF.',
        'status_refreshed' => 'KSeF status refreshed.',
        'not_configured' => 'KSeF is not configured.',
        'unknown_error' => 'Unknown KSeF error.',
        'test_ok' => 'KSeF connection works.',
        'test_failed' => 'KSeF connection failed.',
        'bulk_done' => 'Bulk submission complete.',
        'bulk_done_body' => 'Success: :ok. Errors: :fail.',
    ],

    'status' => [
        'not_submitted' => 'Not submitted',
        'submitted' => 'Submitted',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'error' => 'Error',
    ],

    'table' => [
        'column' => [
            'status' => 'KSeF',
        ],
    ],
];
