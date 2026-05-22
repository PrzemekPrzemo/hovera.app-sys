<?php

declare(strict_types=1);

return [
    'title' => 'Welcome, horse owner',
    'navigation_label' => 'First steps',
    'welcome' => [
        'heading' => 'Step 1 of 3 — your Hovera account',
        'body' => 'Free forever — paid for by the stables and carriers. We will show you 3 essentials: how to add a horse, how to find favourite carriers, and how to order your first transport.',
    ],
    'steps' => [
        'horse' => [
            'title' => 'My first horse',
            'description' => 'Passport, breed, microchip',
            'body' => 'Add your horse — at least name, breed, date of birth, and passport number. Photo + microchip are optional but useful for transport (carriers identify the horse at pickup).',
            'cta_hint' => 'Open in a new tab:',
            'cta' => 'Add a horse',
        ],
        'favorites' => [
            'title' => 'Favourite carriers',
            'description' => 'List of trusted companies',
            'body' => 'After a few transports you will notice carriers you work better with. Save them as Favourites — on a new inquiry, tick "ONLY to my favourites" and the broadcast will not flood the competition.',
            'optional' => '(optional — you can skip and come back later)',
            'cta' => 'Favourite carriers list',
        ],
        'first_order' => [
            'title' => 'First transport order',
            'description' => 'Automatic pricing + 24h offers',
            'body' => 'Fill From → To → date → horse → click "Send". The inquiry goes to all verified carriers in your region. Offers arrive within 24h, you compare prices, click "Accept" — one wins, the rest auto-withdraw.',
            'cta_hint' => 'Open in a new tab:',
            'cta' => 'Order transport',
        ],
    ],
    'action' => [
        'finish' => 'Finish wizard',
        'skip' => 'Skip wizard',
    ],
    'notify' => [
        'completed_title' => 'All set',
        'completed_body' => 'When you need help — the menu has "Help centre → Horse owner" with the full guide.',
        'skipped_title' => 'Wizard skipped',
        'skipped_body' => 'You can always come back to "My horses" or "Order transport" from the menu.',
    ],
];
