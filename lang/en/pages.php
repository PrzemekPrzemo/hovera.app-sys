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
];
