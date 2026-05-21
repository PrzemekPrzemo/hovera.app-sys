<?php

declare(strict_types=1);

return [
    'access' => [
        'not_owner' => 'You are not the owner of the horse this request concerns.',
    ],

    'field' => [
        'name' => 'Horse name',
        'passport_number' => 'Passport number',
        'microchip' => 'Microchip',
    ],

    'status' => [
        'pending' => 'Awaiting decision',
        'accepted' => 'Approved',
        'rejected' => 'Rejected (value reverted)',
    ],
];
