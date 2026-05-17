<?php

declare(strict_types=1);

return [
    'profile' => [
        'navigation' => 'Profile',
        'title' => 'Your profile',
    ],

    'calendar' => [
        'navigation' => "Today's plan",
        'livejumping' => [
            'heading' => 'Competitions (LiveJumping) — next 7 days',
            'more' => 'more',
        ],
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
        'title' => 'Help center',
        'tab' => [
            'manual' => 'User manual',
            'legal' => 'Legal documents',
        ],
        'persona' => [
            'owner' => 'Owner / admin',
            'owner_desc' => 'Full panel, finance, team, stable settings.',
            'employee' => 'Employee / instructor',
            'employee_desc' => 'Calendar, clients, horses — daily operations.',
            'specialist' => 'Vet / specialist',
            'specialist_desc' => 'Health records, visits, horse treatment.',
            'client' => 'Stable client',
            'client_desc' => 'Portal: bookings, passes, my horse.',
        ],
        'legal' => [
            'open_in_new_tab' => 'Open public version',
        ],
        'topbar' => [
            'help' => 'Help center',
            'report_bug' => 'Report a bug / suggestion',
        ],
        'public_lead' => 'Per-role user manuals and full legal documentation for hovera. No login required, 5 languages.',
        'public_cta' => 'Want to try hovera in your stable? 30 days free, no credit card.',
        'public_meta_desc' => 'User manuals, terms, privacy policy and DPA for hovera — equestrian stable management system.',
        'bug_report' => [
            'title' => 'Report a bug or suggestion',
            'lead' => 'Your report goes straight to the hovera team in Todoist — together with the page URL you are on.',
            'kind_label' => 'Type',
            'kind_bug' => 'Bug',
            'kind_idea' => 'Suggestion / change',
            'subject_label' => 'Short title',
            'subject_placeholder' => 'e.g. Cannot delete a pass',
            'description_label' => 'Description',
            'description_placeholder' => 'What happened? What should have happened? Steps to reproduce.',
            'screenshot_label' => 'Screenshot (PNG/JPG, optional)',
            'submit' => 'Send report',
            'cancel' => 'Cancel',
            'success' => 'Thanks — your report has been sent.',
            'error' => 'Sending failed. Please retry or email support@hovera.app.',
        ],
    ],

    'reports' => [
        'month_picker' => 'Month',
        'apply' => 'Apply',
        'empty' => 'No data for the selected month.',
        'col_item' => 'Item',
        'col_total' => 'Net total',

        'revenue' => [
            'navigation' => 'Revenue',
            'title' => 'Monthly revenue report',
            'total_heading' => 'Net total · :month',
            'invoice_count' => 'Invoices in period: :count',
            'top_items' => 'Top 10 items',
            'bucket' => [
                'boarding' => 'Boarding',
                'lessons' => 'Lessons',
                'passes' => 'Passes',
                'other' => 'Other',
            ],
        ],

        'aging' => [
            'navigation' => 'Receivables aging',
            'title' => 'Receivables aging',
            'total_heading' => 'Total overdue',
            'list_heading' => 'List of overdue invoices',
            'empty' => 'No overdue invoices — all paid.',
            'col_invoice' => 'Invoice #',
            'col_client' => 'Client',
            'col_due_at' => 'Due',
            'col_days_overdue' => 'Days overdue',
            'col_amount' => 'Gross',
            'days' => 'days',
            'bucket' => [
                '0_30' => '1–30 days',
                '31_60' => '31–60 days',
                '61_90' => '61–90 days',
                '90_plus' => '> 90 days',
            ],
        ],

        'horse_utilization' => [
            'navigation' => 'Horse utilization',
            'title' => 'Horse utilization',
            'heading' => 'Lessons per horse · :month',
            'subtitle' => 'Confirmed / completed bookings in the selected month. >25 lessons = overwork risk.',
            'col_horse' => 'Horse',
            'col_lessons' => 'Lessons',
            'col_hours' => 'Hours',
        ],

        'instructor_utilization' => [
            'navigation' => 'Instructor utilization',
            'title' => 'Instructor utilization',
            'heading' => 'Hours and attendance · :month',
            'col_instructor' => 'Instructor',
            'col_lessons' => 'Lessons',
            'col_hours' => 'Hours',
            'col_cancelled' => 'Cancelled',
            'col_no_show' => 'No-show',
            'col_attendance' => 'Attendance',
        ],
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
