<?php

declare(strict_types=1);

return [
    'title' => 'Transport quote :number',
    'quote_number' => 'QUOTE NUMBER',
    'accepted_banner' => 'Thank you! Quote accepted — the carrier will be in touch.',
    'rejected_banner' => 'Quote rejected. Thanks for letting us know.',
    'already_accepted' => 'This quote has already been accepted.',
    'already_rejected' => 'This quote has already been rejected.',

    'label' => [
        'from' => 'From',
        'to' => 'To',
        'date' => 'Date',
        'distance' => 'Distance',
        'valid_until' => 'Valid until',
        'net' => 'Net',
        'vat' => 'VAT (:rate%)',
        'gross' => 'Total to pay',
    ],

    'action' => [
        'accept' => 'Accept quote',
        'reject' => 'Reject',
    ],

    'company' => [
        'heading' => 'Invoice',
        'choose_label' => 'Invoice will be issued to:',
        'as_private' => 'Private individual',
        'as_company' => 'Company (business invoice)',
        'company_name_label' => 'Company name',
        'tax_id_label' => 'VAT ID (NIP)',
        'tax_id_placeholder' => '1234567890',
        'address_label' => 'Company address (street, zip, city)',
        'address_placeholder' => 'e.g. 1 Marszalkowska St, 00-001 Warsaw',
        'lookup_action' => 'Fetch from GUS',
        'lookup_loading' => 'Loading…',
        'lookup_success' => 'Data fetched (:sources). Review and adjust if needed.',
        'lookup_not_found' => 'No company found for this VAT ID. Fill in manually.',
        'invalid_nip' => 'VAT ID is invalid (10 digits + checksum).',
        'lookup_error' => 'Failed to fetch data. Fill in manually.',
    ],

    'payment' => [
        'heading' => 'Payment',
        'disclaimer' => 'Payment is processed DIRECTLY to :transporter. Hovera is a marketplace intermediary and does NOT accept payments. Direct any payment disputes straight to the carrier.',
        'confirmed' => 'Payment confirmed by carrier (:date)',
        'pay_now' => 'Pay now (:amount :currency)',
        'instructions_heading' => 'Payment instructions:',
        'contact_transporter' => 'Contact :transporter to arrange payment.',
    ],

    'footer' => 'Secure page powered by :app',

    'disclaimer_intermediary_html' => '<strong>By accepting this quote you enter into a contract DIRECTLY with :transporter_name :transporter_nip.</strong> Hovera is a marketplace intermediary — NOT a party to this contract, NOT a carrier, and NOT liable for the transport. Please read the <a href="/regulamin-marketplace" target="_blank" style="color:inherit;text-decoration:underline;">transport marketplace terms</a>.',
];
