<?php

declare(strict_types=1);

return [
    'profile' => [
        'navigation' => 'Profile',
        'title' => 'Your profile',
    ],

    'calendar' => [
        'navigation' => "Today's plan",
    ],

    'tenant_settings' => [
        'navigation' => 'Stable settings',
        'title' => 'Stable settings',
    ],

    'invoicing_settings' => [
        'navigation' => 'Invoicing & billing',
        'title' => 'Invoicing & billing',
    ],

    'payment_settings' => [
        'navigation' => 'Online payments',
        'title' => 'Online payments',
    ],

    'ksef_settings' => [
        'navigation' => 'KSeF (e-invoices)',
        'title' => 'KSeF — Polish national e-invoicing system',
    ],

    'company_lookup' => [
        'navigation' => 'GUS / KRS',
        'title' => 'Company verification — GUS / KRS',
    ],

    'my_tasks' => [
        'navigation' => 'My tasks',
        'title' => 'My tasks',
        'signed_in_as' => 'Signed in as specialist',
        'sections' => [
            'overdue' => 'Overdue',
            'upcoming' => 'Upcoming (30 days)',
            'recent' => 'Recently performed (30 days)',
        ],
        'empty' => [
            'overdue' => 'No overdue tasks — well done!',
            'upcoming' => 'No procedures scheduled in the next 30 days.',
            'recent' => 'No entries in the last 30 days.',
        ],
        'overdue_by_days' => '{1} overdue by 1 day|[2,*] overdue by :days days',
        'in_days' => '{0} today|{1} tomorrow|[2,*] in :days days',
    ],

    'help' => [
        'navigation' => 'Help',
        'title' => 'User manual',
    ],

    'bulk_invoicing' => [
        'navigation' => 'Monthly bulk invoicing',
        'title' => 'Bulk invoicing — monthly boarding charges',
        'month_picker' => 'Billing month',
        'refresh' => 'Refresh preview',
        'helper' => 'Generates a Draft invoice per client based on each horse\'s active boarding services. Passes are invoiced at sale and excluded from bulk. Each Draft is reviewed and Issued individually in Invoices.',
        'preview_heading' => 'Preview · :month · :count clients',
        'empty' => 'No charges for the selected month. Check if your horses have active boarding services for that period.',
        'items_suffix' => 'items',
        'col_item' => 'Item',
        'col_qty' => 'Qty',
        'col_unit_price' => 'Unit price',
        'col_net' => 'Net',
        'col_gross' => 'Gross',
        'totals' => 'Total (selected or all):',
        'net_short' => 'net',
        'gross_short' => 'gross',
        'actions' => [
            'generate' => 'Generate Drafts',
        ],
        'confirm' => [
            'heading' => 'Generate invoice Drafts?',
            'description' => 'We will create Draft invoices for :month for the selected clients (or all in the preview). Each one is Issued separately in Invoices.',
            'submit' => 'Yes, generate',
        ],
        'flash' => [
            'success' => ':count Drafts generated. Open Invoices to issue them.',
        ],
    ],
];
