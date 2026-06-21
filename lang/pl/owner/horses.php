<?php

declare(strict_types=1);

return [
    'navigation' => 'Moje konie',

    'model' => [
        'singular' => 'koń',
        'plural' => 'konie',
    ],

    'form' => [
        'section' => [
            'identification' => 'Identyfikacja',
            'notes' => 'Notatki',
        ],
        'label' => [
            'name' => 'Imię',
            'breed' => 'Rasa',
            'birth_date' => 'Data urodzenia',
            'sex' => 'Płeć',
            'color' => 'Maść',
            'passport_number' => 'Numer paszportu',
            'microchip' => 'Numer chip',
            'notes' => 'Notatki',
        ],
    ],

    'table' => [
        'name' => 'Imię',
        'breed' => 'Rasa',
        'birth_date' => 'Data urodzenia',
        'sex' => 'Płeć',
        'passport_number' => 'Paszport',
    ],

    'sex' => [
        'mare' => 'Klacz',
        'stallion' => 'Ogier',
        'gelding' => 'Wałach',
        'filly' => 'Klaczka',
        'colt' => 'Ogierek',
        'foal' => 'Źrebię',
    ],

    'empty' => [
        'heading' => 'Brak koni w kartotece',
        'description' => 'Dodaj swojego pierwszego konia, by szybciej składać zamówienia transportu.',
    ],

    'action' => [
        'order_transport' => 'Zamów transport tego konia',
        'view_details' => [
            'label' => 'Szczegóły boardingu',
        ],
        'connect' => [
            'label' => 'Połącz ze stajnią',
            'stable_label' => 'Stajnia',
            'stable_helper' => 'Wpisz nazwę — pokażemy zweryfikowane stajnie używające Hovery.',
            'modal_heading' => 'Połącz „:horse" ze stajnią',
            'modal_description' => 'Wyślij prośbę o pensjonariusza. Stajnia widzi to w swoim panelu i klika „Akceptuj" — wtedy zaczyna prowadzić dziennik wizyt, podkuć, lekcji dla Twojego konia.',
            'notify_invalid_stable' => 'Wybrana stajnia jest niedostępna.',
            'notify_no_central' => 'Ten koń nie jest jeszcze zsynchronizowany z centralnym rejestrem — edytuj go i zapisz ponownie.',
            'notify_requested_title' => 'Prośba wysłana',
            'notify_requested_body' => 'Stajnia „:stable" otrzyma powiadomienie. Po akceptacji koń pojawi się w jej panelu.',
            'notify_already_active_title' => 'Koń jest już połączony',
            'notify_already_active_body' => 'Boarding ze stajnią „:stable" jest już aktywny.',
        ],
    ],
];
