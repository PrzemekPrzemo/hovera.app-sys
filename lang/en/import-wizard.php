<?php

declare(strict_types=1);

return [
    'navigation' => 'Data import',
    'title' => 'Excel/CSV data import',
    'intro' => 'Import a list of clients or horses from an Excel sheet or CSV file. Supported sources: Nasza Stajnia exports, Horstable exports, or any file with headers in row 1.',

    'template' => [
        'clients' => 'Download template — clients',
        'horses' => 'Download template — horses',
    ],

    'steps' => [
        'entity' => [
            'title' => 'What are you importing?',
            'description' => 'Pick the type of records to import.',
        ],
        'file' => [
            'title' => 'Upload file',
            'description' => 'Accepts .xlsx, .xls, .csv (max 10 MB).',
        ],
        'mapping' => [
            'title' => 'Column mapping',
            'description' => 'Match the file columns to hovera fields.',
        ],
        'preview' => [
            'title' => 'Preview & import',
            'description' => 'Verify the first 5 rows before running the import.',
        ],
    ],

    'fields' => [
        'entity' => 'Record type',
        'file' => 'Data file',
        'clients' => [
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'email' => 'Email',
            'phone' => 'Phone',
            'street' => 'Street',
            'postal_code' => 'Postal code',
            'city' => 'City',
            'tax_id' => 'Tax ID',
            'notes' => 'Notes',
        ],
        'horses' => [
            'name' => 'Horse name',
            'breed' => 'Breed',
            'sex' => 'Sex',
            'color' => 'Colour',
            'birth_date' => 'Date of birth',
            'microchip' => 'Microchip',
            'passport_number' => 'Passport number',
            'client_email' => 'Owner email',
            'notes' => 'Notes',
        ],
    ],

    'entity' => [
        'clients' => 'Clients',
        'clients_hint' => 'Horse owners / boarders.',
        'horses' => 'Horses',
        'horses_hint' => 'Boarded and riding-school horses.',
    ],

    'skip' => 'skip',
    'upload_first' => 'Upload a file in the previous step to map columns.',
    'parse_pending' => 'Waiting for a file...',
    'parse_summary' => 'Detected :rows data rows across :cols columns.',
    'parse_failed' => 'Could not read the file',
    'no_file' => 'No file — go back to step 2.',

    'preview' => [
        'empty' => 'No data to display.',
        'status' => 'Status',
        'ok' => 'OK',
        'note' => 'Above are the first 5 rows. The remaining rows will be validated during import — rows with errors are skipped and listed in the summary.',
    ],

    'actions' => [
        'import' => 'Import',
    ],

    'flash' => [
        'success' => 'Imported :count records.',
        'failed' => 'Skipped :count rows with errors.',
    ],

    'result' => [
        'heading' => 'Import result',
        'summary' => 'Imported: :ok · Skipped: :failed.',
    ],
];
