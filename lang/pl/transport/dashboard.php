<?php

declare(strict_types=1);

return [
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
