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
];
