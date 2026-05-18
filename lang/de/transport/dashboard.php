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

    'leads_kpi' => [
        'leads_week' => 'Leads (7 Tage)',
        'leads_week_desc' => 'Erhaltene Anfragen in der letzten Woche.',
        'win_rate' => 'Win Rate (30 Tage)',
        'win_rate_desc' => 'Angenommen / alle Antworten in 30 Tagen.',
        'win_rate_no_data' => 'Keine Daten in den letzten 30 Tagen.',
        'vs_prev' => ':delta zum Vorzeitraum',
    ],

    'upcoming_week' => [
        'heading' => 'Transporte der nächsten Woche',
        'description' => 'Angenommene Angebote mit Servicedatum in den nächsten 7 Tagen.',
        'date' => 'Datum',
        'customer' => 'Kunde',
        'route' => 'Strecke',
        'driver' => 'Fahrer',
        'gross' => 'Brutto',
        'view' => 'Öffnen',
        'empty_heading' => 'Keine geplanten Transporte',
        'empty_description' => 'Nichts für die nächsten 7 Tage — kalkulieren Sie einen neuen Auftrag.',
        'empty_action' => 'Rechner öffnen',
    ],

    'top_paid' => [
        'heading' => 'Top 5 bezahlte Rechnungen (90 Tage)',
        'description' => 'Die größten Zahler des letzten Quartals.',
        'number' => 'Nummer',
        'customer' => 'Kunde',
        'paid_at' => 'Bezahlt am',
        'total' => 'Brutto',
        'view' => 'Öffnen',
        'empty_heading' => 'Keine bezahlten Rechnungen',
        'empty_description' => 'In den letzten 90 Tagen wurde keine Rechnung als bezahlt markiert.',
    ],

    'routes_heatmap' => [
        'heading' => 'Top Strecken (Woiwodschaften, 90 Tage)',
        'description' => 'Von → Nach-Paare aus erhaltenen Anfragen — wo Sie tatsächlich operieren.',
        'empty' => 'Keine Daten — Sie haben in den letzten 90 Tagen auf keinen Lead geantwortet.',
    ],
];
