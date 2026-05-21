<?php

declare(strict_types=1);

return [
    'navigation' => 'Zaproszenia do stajni',
    'navigation_group' => 'Konie',

    'model' => [
        'singular' => 'Zaproszenie do stajni',
        'plural' => 'Zaproszenia do stajni',
    ],

    'table' => [
        'column' => [
            'horse' => 'Koń',
            'stable' => 'Stajnia',
            'requested_at' => 'Data zaproszenia',
        ],
        'passport_prefix' => 'Paszport:',
        'no_passport' => 'Brak nr paszportu',
    ],

    'empty' => [
        'heading' => 'Brak oczekujących zaproszeń',
        'description' => 'Gdy stajnia wyśle prośbę o boarding twojego konia, pojawi się tutaj. Możesz wtedy zaakceptować lub odrzucić.',
    ],

    'action' => [
        'accept' => [
            'label' => 'Akceptuj',
            'modal_description' => 'Akceptujesz, że konia „:horse" goszczonej przez stajnię „:stable". Stajnia od tego momentu widzi go w swoim panelu i może rozliczać boarding.',
            'success' => 'Boarding zaakceptowany',
            'success_body' => 'Stajnia „:stable" widzi już Twojego konia w panelu i może go przypisać do boksu.',
            'stable_missing' => 'Wybrana stajnia nie istnieje — odśwież stronę i spróbuj ponownie.',
        ],
        'reject' => [
            'label' => 'Odrzuć',
            'reason_label' => 'Powód odrzucenia (widoczny dla stajni)',
            'success' => 'Zaproszenie odrzucone',
        ],
    ],
];
