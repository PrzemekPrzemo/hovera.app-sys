<?php

declare(strict_types=1);

return [
    'navigation' => 'Home',
    'title' => 'Transport panel',

    'widgets_section' => 'Stats and metrics',

    'hero' => [
        'primary_badge' => 'Main',
        'cta_open' => 'Open',
        'calculator' => [
            'title' => 'Quote a route',
            'body' => 'Calculator: addresses → distance → fuel cost → full quote ready to send to the customer.',
        ],
        'leads' => [
            'title' => 'Inquiry inbox',
            'body_empty' => 'New customer inquiries will appear here — we will also send an email.',
            'body_with_count' => '{1} :count new inquiry waiting.|[2,*] :count new inquiries waiting.',
        ],
        'quotes' => [
            'title' => 'Sent quotes',
            'body_empty' => 'Your quotes sent to customers. Calculator "Save as quote" lands them here.',
            'body_with_count' => '{1} :count quote awaiting customer decision.|[2,*] :count quotes awaiting customer decision.',
        ],
        'invoices' => [
            'title' => 'Invoices',
            'body_empty' => 'Issued VAT invoices. KSeF integration ready — send with one click.',
            'body_with_amount' => 'Unpaid: :amount. Check receivables.',
        ],
    ],

    'onboarding' => [
        'heading' => '🎯 Account setup',
        'intro' => 'Before customers can see your offers, complete the items below. '
            .'LeadDispatcher skips carriers without verification or vehicles.',
        'step' => [
            'verify' => 'Verify PWL documents',
            'verified' => 'Documents verified',
            'add_vehicle' => 'Add your first vehicle',
            'vehicles_done' => 'Vehicle added',
            'add_driver' => 'Add your first driver',
            'drivers_done' => 'Driver added',
            'set_service_areas' => 'Set service areas (provinces)',
            'service_areas_done' => 'Service areas set',
        ],
    ],

    'kpi' => [
        'mrr_month' => 'Revenue this month',
        'mrr_month_desc' => 'Paid invoices since start of month.',
        'receivables' => 'Receivables',
        'receivables_desc' => 'Issued invoices awaiting payment.',
        'overdue' => 'Overdue invoices',
        'overdue_desc' => 'Total amount :sum.',
        'pending_quotes' => 'Quotes awaiting acceptance',
        'pending_quotes_desc' => 'Sent, still within validity window.',
    ],

    'pending_invoices' => [
        'heading' => 'Quotes without invoice yet',
        'description' => 'Accepted quotes you have not invoiced yet.',
        'customer' => 'Customer',
        'accepted_at' => 'Accepted at',
        'gross_total' => 'Gross',
        'issue' => 'Issue invoice',
    ],

    'top_corridors' => [
        'heading' => 'Top corridors',
        'description' => 'Top 10 from→to pairs in your business.',
        'empty' => 'No data yet — you have not issued any quote.',
    ],

    'upcoming' => [
        'heading' => 'Upcoming transports',
        'description' => 'Accepted quotes with service date today or tomorrow.',
        'today' => 'Today',
        'tomorrow' => 'Tomorrow',
        'empty' => 'No transports.',
    ],

    'leads_kpi' => [
        'leads_week' => 'Leads (7 days)',
        'leads_week_desc' => 'Inquiries received in the last week.',
        'win_rate' => 'Win rate (30 days)',
        'win_rate_desc' => 'Accepted / total responses over 30 days.',
        'win_rate_no_data' => 'No data in the last 30 days.',
        'vs_prev' => ':delta vs previous period',
    ],

    'upcoming_week' => [
        'heading' => 'Transports this coming week',
        'description' => 'Accepted quotes with service date within the next 7 days.',
        'date' => 'Date',
        'customer' => 'Customer',
        'route' => 'Route',
        'driver' => 'Driver',
        'gross' => 'Gross',
        'view' => 'Open',
        'empty_heading' => 'No transports scheduled',
        'empty_description' => 'Nothing for the next 7 days — quote a new job.',
        'empty_action' => 'Open calculator',
    ],

    'top_paid' => [
        'heading' => 'Top 5 paid invoices (90 days)',
        'description' => 'Biggest payers from the last quarter.',
        'number' => 'Number',
        'customer' => 'Customer',
        'paid_at' => 'Paid at',
        'total' => 'Gross',
        'view' => 'Open',
        'empty_heading' => 'No paid invoices',
        'empty_description' => 'No invoices marked as paid in the last 90 days.',
    ],

    'routes_heatmap' => [
        'heading' => 'Top routes (voivodeships, 90 days)',
        'description' => 'From → to pairs based on received inquiries — where you actually operate.',
        'empty' => 'No data — you have not responded to any lead in the last 90 days.',
    ],
];
