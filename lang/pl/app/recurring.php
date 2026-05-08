<?php

declare(strict_types=1);

return [
    'days_of_week' => [
        '1' => 'Poniedziałek',
        '2' => 'Wtorek',
        '3' => 'Środa',
        '4' => 'Czwartek',
        '5' => 'Piątek',
        '6' => 'Sobota',
        '0' => 'Niedziela',
    ],

    'form' => [
        'section' => [
            'basic' => 'Podstawowe',
            'recurrence' => 'Powtarzalność',
            'default_resources' => 'Domyślne zasoby',
            'details' => 'Szczegóły',
        ],
        'label' => [
            'name' => 'Nazwa serii',
            'name_placeholder' => 'Szkółka pon. 17:00',
            'type' => 'Typ',
            'starts_time' => 'Godzina rozpoczęcia',
            'duration_minutes' => 'Czas trwania (min)',
            'pattern' => 'Wzorzec',
            'interval' => 'Co ile',
            'days_of_week' => 'Dni tygodnia',
            'recurrence_starts_on' => 'Od',
            'recurrence_ends_on' => 'Do (opcjonalne)',
            'max_occurrences' => 'Limit wystąpień',
            'max_occurrences_placeholder' => 'np. 26',
            'horse' => 'Koń',
            'instructor' => 'Instruktor',
            'arena' => 'Ujeżdżalnia',
            'client' => 'Klient',
            'title' => 'Tytuł zajęć',
            'price' => 'Cena',
            'is_active' => 'Aktywna seria',
            'notes' => 'Notatki',
        ],
        'helper' => [
            'interval' => '1 = każdy, 2 = co drugi…',
            'recurrence_ends_on' => 'Puste = bez końca; expander generuje max 365 wystąpień jednorazowo.',
            'max_occurrences' => 'Alternatywa do daty końcowej.',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Nazwa',
            'type' => 'Typ',
            'pattern' => 'Wzorzec',
            'starts_time' => 'Godz.',
            'duration_minutes' => 'Min',
            'recurrence_starts_on' => 'Od',
            'recurrence_ends_on' => 'Do',
            'recurrence_ends_on_empty' => '— bez końca —',
            'occurrences_count' => 'Wystąpień',
            'is_active' => 'Aktywna',
        ],
        'filter' => [
            'status' => 'Status',
        ],
    ],

    'action' => [
        'expand' => [
            'label' => 'Wygeneruj wystąpienia',
            'success_title' => 'Seria rozwinięta',
            'success_body' => 'Utworzono :count wystąpień.',
            'skipped' => ' Pominięto z powodu konfliktu: :list.',
        ],
        'cancel_series' => [
            'label' => 'Anuluj serię',
            'modal_heading' => 'Anuluj całą serię',
            'modal_description' => 'Wystąpienia w przeszłości zostaną zachowane, przyszłe odwołane.',
            'success_title' => 'Seria anulowana',
            'success_body' => 'Anulowano :count przyszłych wystąpień.',
        ],
    ],
];
