<?php

declare(strict_types=1);

return [
    'navigation' => 'Panel główny',
    'title' => 'Panel transportu',

    'hero' => [
        'primary_badge' => 'Najważniejsze',
        'calculator' => [
            'title' => 'Wyceń trasę',
            'body' => 'Kalkulator: adresy → odległość → koszt paliwa → pełna wycena gotowa do wysłania klientowi.',
        ],
        'leads' => [
            'title' => 'Inbox zapytań',
            'body_empty' => 'Nowe zapytania od klientów pojawią się tutaj — powiadomimy też mailem.',
            'body_with_count' => '{1} :count nowe zapytanie czeka na odpowiedź.|[2,4] :count nowe zapytania czekają na odpowiedź.|[5,*] :count nowych zapytań czeka na odpowiedź.',
        ],
        'quotes' => [
            'title' => 'Wysłane oferty',
            'body_empty' => 'Twoje oferty wysłane do klientów. Z kalkulatora „Zapisz jako oferta" trafiają tu.',
            'body_with_count' => '{1} :count oferta czeka na decyzję klienta.|[2,4] :count oferty czekają na decyzję klienta.|[5,*] :count ofert czeka na decyzję klienta.',
        ],
        'invoices' => [
            'title' => 'Faktury',
            'body_empty' => 'Wystawione faktury VAT. KSeF integration gotowa — wyślij jednym kliknięciem.',
            'body_with_amount' => 'Nieopłacone: :amount. Sprawdź należności.',
        ],
    ],

    'onboarding' => [
        'heading' => '🎯 Konfiguracja konta',
        'intro' => 'Zanim klienci zaczną widzieć Twoje oferty, uzupełnij poniższe. '
            .'LeadDispatcher pomija transporterów bez weryfikacji lub bez pojazdów.',
        'step' => [
            'verify' => 'Zweryfikuj dokumenty PWL',
            'verified' => 'Dokumenty zweryfikowane',
            'add_vehicle' => 'Dodaj pierwszy pojazd',
            'vehicles_done' => 'Pojazd dodany',
            'add_driver' => 'Dodaj pierwszego kierowcę',
            'drivers_done' => 'Kierowca dodany',
            'set_service_areas' => 'Ustaw obszary działania (województwa)',
            'service_areas_done' => 'Obszary działania ustawione',
        ],
    ],

    'kpi' => [
        'mrr_month' => 'Przychody bieżący miesiąc',
        'mrr_month_desc' => 'Zapłacone faktury (od początku miesiąca).',
        'receivables' => 'Należności',
        'receivables_desc' => 'Wystawione FV oczekujące na zapłatę.',
        'overdue' => 'Przeterminowane FV',
        'overdue_desc' => 'Łączna kwota :sum.',
        'pending_quotes' => 'Oferty czekają na akceptację',
        'pending_quotes_desc' => 'Wysłane, w okresie ważności.',
    ],

    'pending_invoices' => [
        'heading' => 'Oferty bez wystawionej FV',
        'description' => 'Zaakceptowane oferty, dla których jeszcze nie wystawiłeś faktury.',
        'customer' => 'Klient',
        'accepted_at' => 'Zaakceptowano',
        'gross_total' => 'Brutto',
        'issue' => 'Wystaw FV',
    ],

    'top_corridors' => [
        'heading' => 'Najczęstsze korytarze',
        'description' => 'Top 10 par „skąd → dokąd" w Twoim biznesie.',
        'empty' => 'Brak danych — jeszcze nie wystawiłeś żadnej oferty.',
    ],

    'upcoming' => [
        'heading' => 'Najbliższe transporty',
        'description' => 'Zaakceptowane oferty z datą realizacji dziś lub jutro.',
        'today' => 'Dziś',
        'tomorrow' => 'Jutro',
        'empty' => 'Brak transportów.',
    ],

    'leads_kpi' => [
        'leads_week' => 'Leady (7 dni)',
        'leads_week_desc' => 'Otrzymane zapytania w ostatnim tygodniu.',
        'win_rate' => 'Win rate (30 dni)',
        'win_rate_desc' => 'Zaakceptowane / wszystkie odpowiedzi z 30 dni.',
        'win_rate_no_data' => 'Brak danych w ostatnich 30 dniach.',
        'vs_prev' => ':delta vs poprzedni okres',
    ],

    'upcoming_week' => [
        'heading' => 'Transporty w najbliższym tygodniu',
        'description' => 'Zaakceptowane oferty z datą realizacji w ciągu najbliższych 7 dni.',
        'date' => 'Data',
        'customer' => 'Klient',
        'route' => 'Trasa',
        'driver' => 'Kierowca',
        'gross' => 'Brutto',
        'view' => 'Otwórz',
        'empty_heading' => 'Brak zaplanowanych transportów',
        'empty_description' => 'Nic na najbliższe 7 dni — wyceń kolejne zlecenie.',
        'empty_action' => 'Otwórz kalkulator',
    ],

    'top_paid' => [
        'heading' => 'Top 5 zapłaconych FV (90 dni)',
        'description' => 'Najwięksi płatnicy z ostatniego kwartału.',
        'number' => 'Numer',
        'customer' => 'Klient',
        'paid_at' => 'Zapłacono',
        'total' => 'Brutto',
        'view' => 'Otwórz',
        'empty_heading' => 'Brak zapłaconych FV',
        'empty_description' => 'W ostatnich 90 dniach żadna faktura nie została oznaczona jako zapłacona.',
    ],

    'routes_heatmap' => [
        'heading' => 'Top trasy (województwa, 90 dni)',
        'description' => 'Pary "skąd → dokąd" z otrzymanych zapytań — gdzie firma faktycznie operuje.',
        'empty' => 'Brak danych — w ostatnich 90 dniach nie odpowiedziałeś na żaden lead.',
    ],
];
