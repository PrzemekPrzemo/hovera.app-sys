<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'time_type' => 'Czas i typ',
            'resources' => 'Zasoby',
            'details' => 'Szczegóły',
            'participants' => 'Uczestnicy lekcji grupowej',
            'participants_description' => 'Każdy uczestnik = klient + opcjonalnie koń. Po lekcji oznaczasz frekwencję per uczestnik.',
        ],
        'label' => [
            'type' => 'Typ',
            'starts_at' => 'Początek',
            'ends_at' => 'Koniec',
            'horse' => 'Koń',
            'instructor' => 'Instruktor',
            'arena' => 'Ujeżdżalnia',
            'client' => 'Klient',
            'title' => 'Tytuł (dla wydarzeń / blokad)',
            'status' => 'Status',
            'price' => 'Cena',
            'notes' => 'Notatki',
            'participants' => 'Uczestnicy',
            'participant_client' => 'Klient',
            'participant_horse' => 'Koń (opcjonalnie)',
            'participant_horse_placeholder' => '— jedzie na własnym / przydzielimy później —',
            'participant_attendance' => 'Frekwencja',
            'participant_notes' => 'Notatki (np. „pierwsza lekcja")',
        ],
    ],

    'attendance' => [
        'expected' => 'Oczekiwany',
        'present' => 'Obecny',
        'absent' => 'Nieobecny',
        'late' => 'Spóźniony',
    ],

    'actions' => [
        'add_participant' => '+ Dodaj uczestnika',
    ],

    'followup' => [
        'title' => 'Lekcja zakończona — zaplanować kolejną?',
        'body' => 'Sugerowany termin: :date (za tydzień).',
        'cta' => 'Zaplanuj kolejną',
    ],

    'table' => [
        'column' => [
            'starts_at' => 'Początek',
            'ends_at' => 'Koniec',
            'type' => 'Typ',
            'horse' => 'Koń',
            'instructor' => 'Instruktor',
            'arena' => 'Ujeżdżalnia',
            'client' => 'Klient',
            'status' => 'Status',
        ],
        'participant_count' => '{0} brak uczestników|{1} 👥 :count uczestnik|[2,4] 👥 :count uczestników|[5,*] 👥 :count uczestników',
        'filter' => [
            'horse' => 'Koń',
            'instructor' => 'Instruktor',
            'upcoming' => 'Tylko nadchodzące',
        ],
    ],
];
