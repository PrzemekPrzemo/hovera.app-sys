<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'invoice_data' => 'Invoice data',
            'buyer' => 'Buyer',
            'seller' => 'Seller (snapshot)',
            'dates' => 'Dates',
            'items' => 'Line items',
            'notes' => 'Notes',
        ],
        'label' => [
            'kind' => 'Kind',
            'number' => 'Number',
            'number_placeholder' => '— assigned on issue —',
            'status' => 'Status',
            'client' => 'Client',
            'buyer_type' => 'Buyer type',
            'buyer_source' => 'Buyer data source',
            'buyer_name' => 'Name / full name',
            'buyer_nip' => 'Tax ID',
            'buyer_address' => 'Address',
            'buyer_postal_code' => 'Postal code',
            'buyer_city' => 'City',
            'buyer_country' => 'Country',
            'seller_name' => 'Name',
            'seller_nip' => 'Tax ID',
            'seller_address' => 'Address',
            'seller_postal_code' => 'Postal code',
            'seller_city' => 'City',
            'seller_country' => 'Country',
            'issued_at' => 'Issued',
            'sale_date' => 'Sale date',
            'due_at' => 'Due date',
            'item_name' => 'Name',
            'item_quantity' => 'Qty',
            'item_unit' => 'Unit',
            'item_unit_price' => 'Net unit price',
            'item_vat' => 'VAT',
            'notes_label' => 'Notes',
        ],
        'buyer_type' => [
            'individual' => 'Individual',
            'individual_hint' => 'Invoice without VAT ID — name only (private individual, not running a business).',
            'company' => 'Company / sole trader',
            'company_hint' => 'Business invoice — VAT ID, name and address required.',
        ],
        'buyer_source' => [
            'client' => 'Existing client',
            'client_hint' => 'Pick a client from the database — buyer details auto-fill from their record.',
            'adhoc' => 'One-off buyer (ad-hoc)',
            'adhoc_hint' => 'Invoice for a person/company not yet in the clients list — fill in details manually. You can use "Look up in GUS" to autofill the address by VAT ID.',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Number',
            'kind' => 'Kind',
            'issued_at' => 'Issued',
            'client' => 'Buyer',
            'total' => 'Gross',
            'status' => 'Status',
            'due_at' => 'Due',
        ],
        'filter' => [
            'overdue' => 'Overdue',
        ],
    ],

    'action' => [
        'issue' => [
            'label' => 'Issue',
            'success' => 'Invoice issued',
            'failure_title' => "Can't issue invoice",
        ],
        'correct' => [
            'label' => 'Correction',
            'success_title' => 'Correction created',
            'success_body' => 'Open draft :id and edit the line items.',
            'failure_title' => 'Error',
        ],
        'ksef' => [
            'label' => 'Send to KSeF',
            'modal_description' => 'The invoice will be signed with the stable certificate and sent to KSeF.',
            'auth_success_title' => 'KSeF: authentication succeeded',
            'auth_success_body' => 'Invoice payload upload coming up (PR 4b).',
            'failure_title' => 'KSeF: error',
        ],
        'email' => [
            'label' => 'Send by email',
            'modal_description' => "We'll email a link to the invoice. The link is valid for up to 90 days (or 14 days past the due date).",
            'no_email' => 'No client email',
            'success' => 'Invoice emailed to client',
        ],
    ],

    'bulk_action' => [
        'email' => [
            'label' => 'Email clients',
            'modal_description' => 'We will email an invoice link to each selected client (only issued/paid invoices with a client email). Invoices already emailed are skipped unless you tick "Resend".',
            'force_label' => 'Resend',
            'force_helper' => 'By default we skip invoices already emailed once. Tick this if a client did not receive the message and you want it re-sent.',
            'success_title' => 'Sending queued',
            'success_body' => 'Queued :queued emails, skipped :skipped (already sent or draft).',
        ],
    ],
];
