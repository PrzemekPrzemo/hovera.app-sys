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
];
