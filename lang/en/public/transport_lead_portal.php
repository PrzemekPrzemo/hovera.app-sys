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
        'cta' => 'Create account now',
        'already_logged_in' => 'You are logged in to this account.',
        'view_history' => 'View my inquiry history →',
        'account_exists' => 'An account with this email already exists. Log in to see the history.',
        'login_cta' => 'Log in',
    ],

    'signup_form' => [
        'title' => 'Create a hovera customer account',
        'heading' => 'Create an account to see your inquiry history',
        'intro' => 'We will create an account linked to your email address (:email). After logging in '
            .'you will see the history of all your transport inquiries in one place.',
        'label' => [
            'email' => 'Email',
            'password' => 'Password',
            'password_confirmation' => 'Repeat password',
            'terms' => 'I accept the hovera.app terms and privacy policy',
        ],
        'hint' => [
            'password' => 'Min. 8 characters.',
            'email_locked' => 'The email is permanently linked to your inquiry — it cannot be changed.',
        ],
        'submit' => 'Create account and log in',
        'cancel' => 'Back to the portal',
        'created' => 'Account created — you are now logged in. Here you will find your inquiry history.',
        'errors' => [
            'terms' => 'You must accept the terms to create an account.',
            'honeypot' => 'Bot detected — please try again.',
        ],
    ],

    'my_inquiries' => [
        'title' => 'My transport inquiries',
        'heading' => 'My transport inquiries',
        'empty' => 'You have no inquiries yet. Fill out the form to create your first one.',
        'empty_cta' => 'New inquiry',
        'column' => [
            'date' => 'Inquiry date',
            'route' => 'Route',
            'preferred_date' => 'Preferred date',
            'horses' => 'Horses',
            'status' => 'Status',
            'offers' => 'Offers',
        ],
        'view_link' => 'Open portal',
        'logout' => 'Log out',
    ],

    'footer' => [
        'permanent_link' => 'This link works indefinitely. Keep it in a safe place.',
        'disclaimer_intermediary' => 'Hovera is a marketplace platform — we are not a carrier. '
            .'The transport contract is made directly between you and the chosen carrier.',
    ],
];
