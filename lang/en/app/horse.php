<?php

declare(strict_types=1);

return [
    'sex' => [
        'mare' => 'Mare',
        'gelding' => 'Gelding',
        'stallion' => 'Stallion',
        'breeding_stallion' => 'Breeding stallion',
    ],

    'form' => [
        'section' => [
            'identification' => 'Identification',
            'characteristics' => 'Characteristics',
            'boarding' => 'Boarding — billable services',
            'boarding_description' => 'Pick which pricing items apply to this horse. The client sees them in the portal with the monthly estimate.',
            'notes' => 'Notes',
            'sport' => 'Sport (LiveJumping)',
            'sport_help' => 'Paste the horse profile URL from LiveJumping.com — we will show palmares and upcoming starts.',
        ],
        'label' => [
            'name' => 'Name',
            'owner' => 'Owner',
            'owner_placeholder' => '— stable —',
            'box' => 'Box',
            'box_placeholder' => '— unassigned —',
            'microchip' => 'Microchip',
            'passport_number' => 'Passport no.',
            'ueln' => 'UELN',
            'sex' => 'Sex',
            'breed' => 'Breed',
            'color' => 'Color',
            'birth_date' => 'Birth date',
            'boarding_services' => 'Pricing items',
            'livejumping_profile_url' => 'LiveJumping profile URL',
            'livejumping_palmares' => 'Palmares',
        ],
        'helper' => [
            'box' => 'Changing the box logs history in "Boxes → Assignment history".',
            'ueln' => 'Universal Equine Life Number',
            'boarding_services' => 'Configure pricing in "Stable → Boarding pricing". Per-horse price overrides (e.g. discount) are set there manually after creating the entry.',
            'livejumping_profile_url' => 'Copy the profile page URL from livejumping.com — e.g. https://livejumping.com/horse/12345/romeo',
            'livejumping_no_profile' => 'Paste an LJ profile URL above to see palmares.',
            'livejumping_fetch_failed' => 'Could not fetch data from LiveJumping (check the URL or try again later).',
        ],
        'stats' => [
            'starts' => 'Starts',
            'wins' => 'Wins',
            'placings' => 'Top placings',
            'ranking_points' => 'Ranking points',
            'recent_results' => 'Recent results',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'breed' => 'Breed',
            'sex' => 'Sex',
            'color' => 'Color',
            'birth_date' => 'Born',
            'owner' => 'Owner',
            'owner_placeholder' => '— stable —',
            'created_at' => 'Added',
        ],
    ],

    'action' => [
        'import_from_registry' => [
            'label' => 'Import from registry',
            'modal_heading' => 'Add a horse from the owners registry',
            'modal_description' => 'Enter the owner email — the system will list their horses from the central registry. After picking one we send a boarding request, which the owner accepts in their panel.',
            'owner_email' => 'Owner email',
            'owner_email_helper' => 'Email the owner used to register on Hovera.',
            'horse' => 'Horse',
            'horse_helper' => 'List of horses owned by this account in the central registry.',
            'no_passport' => 'no passport',
            'submit' => 'Send boarding request',
            'no_tenant' => 'No active stable context — try again.',
            'horse_missing' => 'The selected horse no longer exists in the registry.',
            'success_title' => 'Boarding request sent',
            'success_body' => 'Horse ":name" is waiting for the owner to accept. Status: :status.',
            'lookup' => [
                'user_not_found' => 'No owner with this email. Check spelling or ask them to register at /register/horse-owner.',
                'no_horses' => 'Owner :email exists but has no horses in the central registry yet.',
                'found' => 'Found :count horse(s) — pick from the list below.',
            ],
        ],
    ],
];
