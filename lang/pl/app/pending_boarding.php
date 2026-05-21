<?php

declare(strict_types=1);

return [
    'navigation' => 'Prośby o boarding',

    'model' => [
        'singular' => 'Prośba o boarding',
        'plural' => 'Prośby o boarding',
    ],

    'table' => [
        'column' => [
            'horse' => 'Koń',
            'owner' => 'Właściciel',
            'requested_at' => 'Data prośby',
        ],
        'passport_prefix' => 'Paszport:',
        'no_passport' => 'Brak nr paszportu',
    ],

    'empty' => [
        'heading' => 'Brak oczekujących próśb',
        'description' => 'Gdy właściciel konia wyśle prośbę o boarding ze swojego panelu, pojawi się tutaj.',
    ],

    'action' => [
        'accept' => [
            'label' => 'Akceptuj',
            'modal_description' => 'Akceptujesz pensjonat dla konia „:horse" należącego do :owner. Od tej chwili konia widzisz w panelu Hovera, możesz przypisać boks i wystawiać faktury.',
            'success' => 'Boarding zaakceptowany',
            'success_body' => 'Konia „:horse" widzisz teraz w „Konie". Możesz przypisać boks i ustawić cennik.',
            'stable_missing' => 'Stajnia nie istnieje — odśwież stronę.',
        ],
        'reject' => [
            'label' => 'Odrzuć',
            'reason_label' => 'Powód (widoczny dla właściciela)',
            'from_stable' => '(odrzucone przez stajnię :stable)',
            'success' => 'Prośba odrzucona — właściciel został powiadomiony.',
        ],
    ],
];
