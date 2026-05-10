<?php

declare(strict_types=1);

return [
    'action' => [
        'label' => 'Export data (post-trial)',
        'modal_heading' => 'Data export — :name',
        'modal_description' => 'Generates a ZIP containing clients, horses, calendar (.ics), invoices and meta.json. The file is downloaded locally and removed from the server after sending.',
    ],
    'toast' => [
        'success_title' => 'Export ready',
        'success_body' => 'File :file is ready for download.',
        'failure_title' => 'Export failed',
    ],
];
