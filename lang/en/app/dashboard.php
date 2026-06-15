<?php

declare(strict_types=1);

return [
    'today' => [
        'bookings' => 'Bookings today',
        'bookings_desc' => 'Active calendar entries',

        'vacant_boxes' => 'Vacant boxes',
        'vacant_boxes_desc' => 'Active, with capacity',

        'overdue_care' => 'Overdue care',
        'overdue_care_desc' => 'Vaccinations / shoeing / dental past due',

        'unpaid_invoices' => 'Unpaid invoices',
        'unpaid_invoices_desc' => '{0} none outstanding|{1} :count invoice issued|[2,*] :count invoices issued',

        'delta_suffix' => 'vs yesterday',
        'delta_flat' => 'flat vs yesterday',

        'bookings_table_heading' => 'Today\'s bookings',
        'col_time' => 'Time',
        'col_horse' => 'Horse',
        'col_instructor' => 'Instructor',
        'col_arena' => 'Arena',
        'col_status' => 'Status',
        'empty_heading' => 'No bookings today',
        'empty_desc' => 'A quiet day — or time for a promo!',
    ],

    'livejumping' => [
        'heading' => 'Upcoming starts (LiveJumping)',
        'description' => 'Horses and riders from your stable that have LJ profiles set.',
        'empty' => 'No upcoming starts. Add a LiveJumping profile URL on a horse or client card.',
        'more_count' => '+ :count more',
    ],
];
