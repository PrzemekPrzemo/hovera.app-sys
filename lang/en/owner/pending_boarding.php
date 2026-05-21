<?php

declare(strict_types=1);

return [
    'navigation' => 'Stable invitations',
    'navigation_group' => 'Horses',

    'model' => [
        'singular' => 'Stable invitation',
        'plural' => 'Stable invitations',
    ],

    'table' => [
        'column' => [
            'horse' => 'Horse',
            'stable' => 'Stable',
            'requested_at' => 'Invited at',
        ],
        'passport_prefix' => 'Passport:',
        'no_passport' => 'No passport number',
    ],

    'empty' => [
        'heading' => 'No pending invitations',
        'description' => 'When a stable sends a boarding request for your horse, it will appear here. You can then accept or decline.',
    ],

    'action' => [
        'accept' => [
            'label' => 'Accept',
            'modal_description' => 'You accept that horse ":horse" is boarded at stable ":stable". From now on the stable sees the horse in its panel and can bill boarding services.',
            'success' => 'Boarding accepted',
            'success_body' => 'Stable ":stable" now sees your horse in their panel and can assign a box.',
            'stable_missing' => 'The selected stable no longer exists — refresh the page and try again.',
        ],
        'reject' => [
            'label' => 'Decline',
            'reason_label' => 'Reason for decline (visible to the stable)',
            'success' => 'Invitation declined',
        ],
    ],
];
