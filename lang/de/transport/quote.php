<?php

declare(strict_types=1);

return [
    'section' => [
        'header' => 'Kopfdaten',
        'customer' => 'Kunde',
        'route' => 'Strecke',
        'resources' => 'Ressourcen (optional)',
        'pricing' => 'Preisgestaltung',
        'terms' => 'Bedingungen & Notizen',
    ],

    'form' => [
        'label' => [
            'number' => 'Nummer',
            'status' => 'Status',
            'valid_until' => 'Gültig bis',
            'customer_name' => 'Name',
            'customer_email' => 'E-Mail',
            'customer_phone' => 'Telefon',
            'customer_company' => 'Firma',
            'customer_tax_id' => 'USt-ID',
            'customer_address' => 'Rechnungsadresse',
            'pickup_address' => 'Abholadresse',
            'dropoff_address' => 'Lieferadresse',
            'preferred_date' => 'Datum',
            'preferred_time' => 'Uhrzeit',
            'round_trip' => 'Hin- und Rückfahrt',
            'loaded' => 'Beladen (mit Pferd)',
            'vehicle' => 'Fahrzeug',
            'driver' => 'Fahrer',
            'distance_km' => 'Entfernung',
            'rate_per_km' => 'Tarif',
            'duration_seconds' => 'Dauer (s)',
            'base_cost' => 'Grundpreis',
            'fuel_surcharge' => 'Kraftstoffzuschlag',
            'minimum_adjustment' => 'Anpassung Mindestpreis',
            'net_total' => 'Netto',
            'vat_rate' => 'MwSt.-Satz',
            'vat_amount' => 'MwSt.-Betrag',
            'gross_total' => 'Brutto',
            'currency' => 'Währung',
            'routing_provider' => 'Routenquelle',
            'terms' => 'Geschäftsbedingungen',
            'notes' => 'Interne Notizen',
        ],
        'helper' => [
            'terms' => 'Für den Kunden sichtbar im Angebot / PDF.',
            'notes' => 'Nur für das Team — nicht an Kunden weitergegeben.',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Nummer',
            'customer' => 'Kunde',
            'route' => 'Strecke',
            'preferred_date' => 'Datum',
            'gross_total' => 'Brutto',
            'status' => 'Status',
            'created_at' => 'Erstellt',
        ],
    ],

    'action' => [
        'send' => 'An Kunden senden',
        'withdraw' => 'Angebot zurückziehen',
        'download_pdf' => 'PDF herunterladen',
        'issue_invoice' => 'Rechnung ausstellen',
    ],

    'notify' => [
        'sent' => 'Angebot versendet',
        'sent_body' => 'Angebot :number an :email mit PDF im Anhang gesendet.',
        'sent_no_email' => 'Angebot gespeichert, E-Mail jedoch nicht gesendet — prüfen Sie die SMTP-Konfiguration "transport".',
        'sent_no_customer_email' => 'Angebot :number bereit (keine Kunden-E-Mail — PDF herunterladen und manuell senden).',
        'withdrawn' => 'Angebot zurückgezogen',
        'verification_required' => 'Konto nicht verifiziert',
        'verification_required_body' => 'Um ein Angebot zu senden, muss das Konto zuerst verifiziert werden — laden Sie die Pflichtdokumente hoch.',
        'open_documents' => 'Dokumente öffnen',
        'invoice_issued' => 'Rechnung ausgestellt',
        'invoice_issued_body' => 'Rechnung :number aus diesem Angebot erstellt.',
        'invoice_failed' => 'Rechnung konnte nicht erstellt werden',
    ],
];
