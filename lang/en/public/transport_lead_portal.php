<?php

declare(strict_types=1);

return [
    'title' => 'Your transport inquiry',
    'heading' => 'Your transport inquiry',
    'subtitle' => 'Here you can see all carrier offers for this specific inquiry. '
        .'Keep this link — it works permanently. To track the history of all your '
        .'inquiries, create an account (section below).',

    'section' => [
        'summary' => 'Your inquiry',
        'offers' => 'Carrier offers (:count)',
    ],

    'label' => [
        'pickup' => 'Pickup',
        'dropoff' => 'Drop-off',
        'date' => 'Preferred date',
        'horses' => 'Horse count',
        'status' => 'Status',
        'notes' => 'Notes',
    ],

    'status' => [
        'open' => 'Open — waiting for offers',
        'quoted' => 'Offers received',
        'accepted' => 'Offer accepted',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
    ],

    'response' => [
        'accepted' => 'Offer accepted',
        'proposed_date' => 'Proposed date',
    ],

    'no_responses' => 'No offers yet. We will email you as soon as a carrier responds.',
    'transporter_unknown' => 'Carrier (name will load shortly)',

    'signup' => [
        'heading' => '🎁 Create an account to see your full history',
        'body' => 'With an account you can see the history of all your inquiries in one place, '
            .'get push notifications, and submit new inquiries faster (form is pre-filled).',
        'coming_soon' => 'Feature coming soon — we will add the signup form shortly.',
    ],

    'footer' => [
        'permanent_link' => 'This link works indefinitely. Keep it in a safe place.',
        'disclaimer_intermediary' => 'Hovera is a marketplace platform — we are not a carrier. '
            .'The transport contract is made directly between you and the chosen carrier.',
    ],
];
