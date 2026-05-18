<?php

declare(strict_types=1);

return [
    'title' => 'Horse transport inquiry',
    'heading' => 'Horse transport inquiry',
    'subtitle' => 'Fill the form — we will send your request to verified carriers in your region. Offers arrive by email.',
    'errors_heading' => 'Please review:',

    'label' => [
        'customer_name' => 'Full name',
        'customer_email' => 'Email',
        'customer_phone' => 'Phone (optional)',
        'pickup_address' => 'From (pickup)',
        'dropoff_address' => 'To (drop-off)',
        'preferred_date' => 'Preferred date',
        'preferred_time' => 'Time (optional)',
        'flexible_date' => 'Date is flexible (±2 days OK)',
        'horse_count' => 'Number of horses',
        'notes' => 'Additional notes',
        'terms' => 'I consent to share my data with verified carriers for offer preparation. <a href="/polityka-prywatnosci" target="_blank">Privacy policy</a>.',
    ],

    'placeholder' => [
        'pickup_address' => 'e.g. Pegasus Stable, Łąkowa 1, Warsaw',
        'dropoff_address' => 'e.g. Olsztyn, Konna 5',
        'notes' => 'E.g. breeding horses, animal transport license required, OCS insurance...',
    ],

    'action' => [
        'submit' => 'Send inquiry',
    ],

    'error' => [
        'geocoding' => 'Could not find the address: :msg. Try city + street.',
        'terms' => 'You must consent to share data with carriers.',
    ],

    'thanks_title' => 'Inquiry received',
    'thanks_heading' => 'Thank you!',
    'thanks_body' => 'We sent your inquiry to transport companies. Offers arrive at :email within 24 hours.',
    'thanks_reference' => 'Reference number',
];
