<?php

declare(strict_types=1);

return [
    'profile' => [
        'navigation' => 'Profil',
        'title' => 'Twój profil',
    ],

    'calendar' => [
        'navigation' => 'Plan dnia',
    ],

    'tenant_settings' => [
        'navigation' => 'Ustawienia stajni',
        'title' => 'Ustawienia stajni',
    ],

    'invoicing_settings' => [
        'navigation' => 'Faktury i rozliczenia',
        'title' => 'Faktury i rozliczenia',
    ],

    'payment_settings' => [
        'navigation' => 'Płatności online',
        'title' => 'Płatności online',
    ],

    'ksef_settings' => [
        'navigation' => 'KSeF (e-faktury)',
        'title' => 'KSeF — krajowy system e-faktur',
    ],

    'company_lookup' => [
        'navigation' => 'GUS / KRS',
        'title' => 'Weryfikacja firm — GUS / KRS',
    ],

    'my_tasks' => [
        'navigation' => 'Moje zadania',
        'title' => 'Moje zadania',
        'signed_in_as' => 'Zalogowany jako specjalista',
        'sections' => [
            'overdue' => 'Przeterminowane',
            'upcoming' => 'Najbliższe zabiegi (30 dni)',
            'recent' => 'Ostatnio wykonane (30 dni)',
        ],
        'empty' => [
            'overdue' => 'Brak zaległych zadań — gratulacje!',
            'upcoming' => 'Brak zaplanowanych zabiegów w najbliższych 30 dniach.',
            'recent' => 'Brak wpisów z ostatnich 30 dni.',
        ],
        'overdue_by_days' => '{1} przeterminowane o 1 dzień|[2,4] przeterminowane o :days dni|[5,*] przeterminowane o :days dni',
        'in_days' => '{0} dziś|{1} jutro|[2,*] za :days dni',
    ],

    'help' => [
        'navigation' => 'Pomoc',
        'title' => 'Instrukcja obsługi',
    ],

    'reports' => [
        'month_picker' => 'Miesiąc',
        'apply' => 'Pokaż',
        'empty' => 'Brak danych dla wybranego miesiąca.',
        'col_item' => 'Pozycja',
        'col_total' => 'Wartość netto',

        'revenue' => [
            'navigation' => 'Przychody',
            'title' => 'Raport miesięczny — przychody',
            'total_heading' => 'Razem netto · :month',
            'invoice_count' => 'Faktury w okresie: :count',
            'top_items' => 'Top 10 pozycji',
            'bucket' => [
                'boarding' => 'Pensjonat',
                'lessons' => 'Lekcje',
                'passes' => 'Karnety',
                'other' => 'Inne',
            ],
        ],

        'aging' => [
            'navigation' => 'Wiekowanie należności',
            'title' => 'Wiekowanie należności',
            'total_heading' => 'Łącznie zaległe',
            'list_heading' => 'Lista przeterminowanych faktur',
            'empty' => 'Brak zaległych faktur — wszystko opłacone.',
            'col_invoice' => 'Numer FV',
            'col_client' => 'Klient',
            'col_due_at' => 'Termin',
            'col_days_overdue' => 'Dni po terminie',
            'col_amount' => 'Kwota brutto',
            'days' => 'dni',
            'bucket' => [
                '0_30' => '1–30 dni',
                '31_60' => '31–60 dni',
                '61_90' => '61–90 dni',
                '90_plus' => '> 90 dni',
            ],
        ],

        'horse_utilization' => [
            'navigation' => 'Wykorzystanie konia',
            'title' => 'Wykorzystanie konia',
            'heading' => 'Lekcje per koń · :month',
            'subtitle' => 'Liczba potwierdzonych / zakończonych rezerwacji w wybranym miesiącu. Powyżej 25 lekcji = ryzyko przeciążenia.',
            'col_horse' => 'Koń',
            'col_lessons' => 'Lekcje',
            'col_hours' => 'Godziny',
        ],

        'instructor_utilization' => [
            'navigation' => 'Wykorzystanie instruktora',
            'title' => 'Wykorzystanie instruktora',
            'heading' => 'Godziny i frekwencja · :month',
            'col_instructor' => 'Instruktor',
            'col_lessons' => 'Lekcje',
            'col_hours' => 'Godziny',
            'col_cancelled' => 'Odwołane',
            'col_no_show' => 'No-show',
            'col_attendance' => 'Frekwencja',
        ],
    ],
];
