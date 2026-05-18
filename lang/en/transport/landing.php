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

    'footer' => 'Secure page powered by :app',

    'disclaimer_intermediary_html' => '<strong>By accepting this quote you enter into a contract DIRECTLY with :transporter_name :transporter_nip.</strong> Hovera is a marketplace intermediary — NOT a party to this contract, NOT a carrier, and NOT liable for the transport. Please read the <a href="/regulamin-marketplace" target="_blank" style="color:inherit;text-decoration:underline;">transport marketplace terms</a>.',
];
