<?php

declare(strict_types=1);

return [
    'kpi' => [
        'mrr_month' => 'Umsatz diesen Monat',
        'mrr_month_desc' => 'Bezahlte Rechnungen seit Monatsanfang.',
        'receivables' => 'Forderungen',
        'receivables_desc' => 'Offene Rechnungen, Zahlung ausstehend.',
        'overdue' => 'Überfällige Rechnungen',
        'overdue_desc' => 'Gesamtbetrag :sum.',
        'pending_quotes' => 'Angebote in Wartestellung',
        'pending_quotes_desc' => 'Versendet, Gültigkeit aktiv.',
    ],

    'pending_invoices' => [
        'heading' => 'Angebote ohne Rechnung',
        'description' => 'Angenommene Angebote, die noch nicht in Rechnung gestellt sind.',
        'customer' => 'Kunde',
        'accepted_at' => 'Angenommen am',
        'gross_total' => 'Brutto',
        'issue' => 'Rechnung erstellen',
    ],

    'top_corridors' => [
        'heading' => 'Top-Korridore',
        'description' => 'Die 10 häufigsten Von→Nach-Paare in Ihrem Geschäft.',
        'empty' => 'Noch keine Daten — Sie haben noch kein Angebot erstellt.',
    ],

    'upcoming' => [
        'heading' => 'Nächste Transporte',
        'description' => 'Angenommene Angebote mit Servicedatum heute oder morgen.',
        'today' => 'Heute',
        'tomorrow' => 'Morgen',
        'empty' => 'Keine Transporte.',
    ],
];
