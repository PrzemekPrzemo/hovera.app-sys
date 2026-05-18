<?php

declare(strict_types=1);

return [
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
