<?php

declare(strict_types=1);

return [
    'title' => ':horse — :tenant',
    'back' => '← Back to portal',

    'info' => [
        'breed' => 'Breed',
        'sex' => 'Sex',
        'color' => 'Color',
        'age' => 'Age',
        'age_value' => ':years yr (:year)',
        'microchip' => 'Microchip',
        'passport' => 'Passport',
    ],

    'sections' => [
        'boarding' => 'Boarding and costs',
        'photos' => 'Photo gallery',
        'activities' => 'What we do with your horse',
        'messages' => 'Messages with the stable',
        'documents' => 'Documents',
        'health' => 'Veterinary history',
    ],

    'box' => [
        'pill' => '🏠 Box :label',
        'monthly_suffix' => '/mo.',
        'monthly_label' => 'boarding: :rate',
    ],

    'services' => [
        'heading' => 'Billable services',
        'col_item' => 'Item',
        'col_price' => 'Price',
        'col_frequency' => 'Frequency',
        'col_monthly' => '~mo.',
        'price_per_unit' => ':amount zł / :unit',
    ],

    'cost' => [
        'monthly_label' => 'Estimated monthly cost:',
        'monthly_disclaimer' => 'Without "per use" and one-time services — they appear only when actually billed.',
    ],

    'messages' => [
        'sent_flash' => '✓ Message sent — the stable received an email notification.',
        'subject_placeholder' => 'Subject (optional)',
        'body_placeholder' => 'Write something to the stable…',
        'send' => 'Send',
        'you' => 'You',
        'empty' => 'No messages — send the first one.',
        'attachment_fallback' => 'attachment',
    ],

    'documents' => [
        'uploaded_flash' => '✓ Document uploaded.',
        'deleted_flash' => '✓ Document deleted.',
        'name_placeholder' => 'Document name',
        'description_placeholder' => 'Description (optional)',
        'upload' => 'Upload document',
        'uploaded_by_stable' => 'Stable',
        'uploaded_by_you' => 'You',
        'valid_until' => 'valid until:',
        'download' => '📥 Download',
        'delete' => 'Delete',
        'delete_confirm' => 'Delete document?',
        'empty' => 'No documents. Upload the first one.',
    ],

    'health' => [
        'performed_by_label' => 'Performed by: :name',
        'next_due_label' => 'Next due: :date',
        'overdue_pill' => 'Overdue',
        'soon_pill' => 'Soon',
        'empty' => 'No veterinary records.',
    ],
];
