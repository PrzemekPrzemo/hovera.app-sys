<?php

declare(strict_types=1);

return [
    'actions' => [
        'save' => 'Save',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'create' => 'Create',
        'view' => 'View',
        'close' => 'Close',
        'confirm' => 'Confirm',
        'back' => 'Back',
        'next' => 'Next',
        'submit' => 'Submit',
        'search' => 'Search',
        'filter' => 'Filter',
        'reset' => 'Reset',
        'export' => 'Export',
        'import' => 'Import',
        'download' => 'Download',
        'upload' => 'Upload',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'Pending',
        'archived' => 'Archived',
        'deleted' => 'Deleted',
        'trashed' => 'Trashed',
    ],

    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'address' => 'Address',
        'website' => 'Website',
        'description' => 'Description',
        'notes' => 'Notes',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
        'deleted_at' => 'Deleted',
    ],

    'language' => [
        'switcher' => 'Language',
        'pl' => 'Polski',
        'en' => 'English',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'ru' => 'Русский',
    ],

    'field' => [
        'locale' => 'Preferred language',
        'locale_help' => 'Choose the default UI language. You can change it anytime.',
    ],

    'form' => [
        'errors_heading' => 'Please fix the errors below:',
    ],

    'yes' => 'Yes',
    'no' => 'No',
    'none' => 'None',
    'all' => 'All',
    'or' => 'or',
    'dismiss' => 'Dismiss',

    'gus_lookup' => [
        'label' => 'Fetch from GUS / VIES',
        'invalid_nip' => 'VAT ID is invalid (10 digits + checksum, or EU prefix e.g. DE123456789).',
        'not_found' => 'No company found for this VAT ID. Check the spelling or fill in manually.',
        'success' => 'Data fetched',
        'success_body' => 'Source: :sources. Verify the address and adjust if needed.',
    ],

    'vies_lookup' => [
        'invalid_vat' => 'EU VAT number was not found in VIES (invalid or inactive).',
    ],
];
