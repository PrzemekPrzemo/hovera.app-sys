<?php

declare(strict_types=1);

return [
    'order' => [
        'navigation' => 'Order transport',
        'title' => 'Order transport',
        'heading' => 'New transport order',

        'section' => [
            'horse' => 'Horse',
            'route' => 'Route and date',
            'notes' => 'Notes for the carrier',
        ],

        'label' => [
            'horse' => 'Select horse',
            'pickup' => 'Pickup address',
            'dropoff' => 'Drop-off address',
            'preferred_date' => 'Preferred date',
            'preferred_time' => 'Preferred time',
            'mode' => 'Trip mode',
            'notes' => 'Additional information',
        ],

        'placeholder' => [
            'horse' => 'Unassigned (mention in notes)',
            'pickup' => 'e.g. 1 Stable Lane, 02-123 Warsaw',
            'dropoff' => 'e.g. Sopot Racecourse, Polanki 91',
            'notes' => 'Special needs of the horse, availability windows, etc.',
        ],

        'helper' => [
            'horse' => 'You can add a horse first under "My horses".',
            'mode' => 'One way / round trip / carrier returns to base.',
        ],

        'action' => [
            'submit' => 'Send request to carriers',
        ],

        'info' => [
            'how_it_works' => 'Your request will be sent to verified carriers serving the route. Offers will appear under "My orders" — compare prices and pick a carrier.',
        ],

        'notify' => [
            'success_title' => 'Request sent',
            'success_body' => 'Carriers in your region have been notified. Their offers will appear here.',
            'failed_title' => 'Could not create the order',
            'failed_body' => 'Please try again in a moment. If the problem persists, contact us.',
            'geocoding_failed_title' => 'We could not recognise the address',
        ],
    ],

    'orders' => [
        'navigation' => 'My orders',

        'model' => [
            'singular' => 'transport order',
            'plural' => 'my orders',
        ],

        'section' => [
            'route' => 'Route',
            'horse' => 'Horse',
            'notes' => 'Notes',
            'lifecycle' => 'Status',
            'responses' => 'Offers from carriers',
            'responses_description' => 'Number of offers received for this request.',
        ],

        'label' => [
            'pickup' => 'Pickup address',
            'dropoff' => 'Drop-off address',
            'preferred_date' => 'Date',
            'preferred_time' => 'Time',
            'mode' => 'Mode',
            'horse' => 'Horse',
            'status' => 'Status',
            'created_at' => 'Created',
        ],

        'table' => [
            'date' => 'Date',
            'pickup' => 'From',
            'dropoff' => 'To',
            'horse' => 'Horse',
            'mode' => 'Mode',
            'status' => 'Status',
        ],

        'status' => [
            'draft' => 'Draft',
            'open' => 'Open',
            'quoted' => 'Offers received',
            'accepted' => 'Accepted',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled',
        ],

        'responses' => [
            'none' => 'No offers yet. We will let you know when one arrives.',
            'count' => ':count new offers to compare.',
            'lead_missing' => 'Could not fetch details. Contact us if the issue persists.',
        ],

        'empty' => [
            'heading' => 'No transport orders',
            'description' => 'Place your first request — compare offers from verified carriers.',
            'cta' => 'Order transport',
        ],

        'action' => [
            'create' => 'New order',
        ],
    ],

    'widget' => [
        'upcoming' => [
            'heading' => 'Upcoming transport',
            'description' => 'Open and accepted orders for the nearest date.',
            'empty' => 'You have no upcoming transport orders.',
            'cta' => 'Order transport',
        ],
    ],

    'notifications' => [
        'new_offers' => 'New offers',
        'new_offers_description' => 'Total carrier responses on your orders',
        'accepted' => 'Accepted',
        'accepted_description' => 'In the last 14 days',
        'upcoming' => 'Within 3 days',
        'upcoming_description' => 'Transport scheduled for the next few days',
    ],
];
