<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'data' => 'Dane instruktora',
        ],
        'label' => [
            'name' => 'Imię i nazwisko',
            'phone' => 'Telefon',
            'hourly_rate' => 'Stawka za godzinę',
            'color' => 'Kolor w kalendarzu',
            'is_active' => 'Aktywny',
            'notes' => 'Notatki',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Imię i nazwisko',
            'phone' => 'Telefon',
            'hourly_rate' => 'Stawka',
            'color' => 'Kolor',
            'is_active' => 'Aktywny',
        ],
        'filter' => [
            'status' => 'Status',
        ],
    ],

    'actions' => [
        'ics_url' => 'Kalendarz .ics',
    ],
    'ics_modal' => [
        'heading' => 'Kalendarz instruktora :name',
        'description' => 'Skopiuj URL i wklej w Google Calendar / Outlook / Apple Calendar jako "Dodaj kalendarz przez URL". Lekcje pojawią się automatycznie i będą synchronizować się co kilka godzin.',
        'url_label' => 'URL feedu (subskrypcja)',
        'howto' => 'Google Calendar → "Inne kalendarze" → "+ → Z URL" → wklej URL. Outlook → "Dodaj kalendarz → Subskrybuj z internetu". Apple → File → New Calendar Subscription.',
        'token_ensured' => 'URL gotowy',
        'close' => 'Zamknij',
    ],
];
