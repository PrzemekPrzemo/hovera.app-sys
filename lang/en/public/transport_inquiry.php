<?php

declare(strict_types=1);

return [
    'title' => 'Horse transport inquiry',
    'heading' => 'Horse transport inquiry',
    'subtitle' => 'Fill the form — we will send your request to verified carriers in your region. Offers arrive by email.',
    'errors_heading' => 'Please review:',

    'direct_target_banner' => 'You are sending this inquiry directly to :name. Only this company will reply.',
    'direct_target_switch_to_broadcast' => 'I prefer to send it to all matching carriers',

    'originator_banner' => [
        'from_stable' => 'Request from stable :name',
        'back_to_app' => 'back to panel',
    ],

    'prefill' => [
        'horse_note' => 'Horse: :name',
    ],

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
        'client_for' => 'Quote client',
        'terms' => 'I consent to share my data with verified carriers for offer preparation. <a href="/polityka-prywatnosci" target="_blank">Privacy policy</a>.',
    ],

    'client_for' => [
        'stable' => 'Stable (me)',
        'boarder_prefix' => 'Boarder: ',
        'helper' => 'Pick a boarder if this transport is organised for a boarder — the invoice for the accepted offer will go to the horse owner, not the stable.',
    ],

    'boarder' => [
        'unknown_horse' => '(unknown horse)',
        'unknown_owner' => '(unknown owner)',
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

    'disclaimer_intermediary' => 'Hovera is a marketplace intermediary — it is not a carrier and does not perform transports. The transport contract is concluded DIRECTLY between you and the selected transporter after you accept their quote. Details in the <a href="/regulamin-marketplace" target="_blank">transport marketplace terms</a>.',
    'disclaimer_intermediary_thanks' => 'The chosen transporter will contact you directly — they are the party to the transport contract. Hovera is only a technology intermediary, not a party and not liable for the transport. Details: <a href="/regulamin-marketplace" target="_blank">marketplace terms</a>.',
];
