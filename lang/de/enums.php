<?php

declare(strict_types=1);

return [
    'boarding_frequency' => [
        'daily' => 'Täglich',
        'monthly' => 'Monatlich',
        'per_use' => 'Pro Nutzung',
        'once' => 'Einmalig',
    ],

    'calendar_entry_status' => [
        'requested' => 'Angefragt',
        'confirmed' => 'Bestätigt',
        'cancelled' => 'Storniert',
        'completed' => 'Abgeschlossen',
        'no_show' => 'Nicht erschienen',
    ],

    'calendar_entry_type' => [
        'lesson_individual' => 'Einzelreitstunde',
        'lesson_group' => 'Gruppenreitstunde',
        'training' => 'Training',
        'care' => 'Pflege (Tierarzt/Hufschmied)',
        'event' => 'Veranstaltung',
        'block' => 'Sperre',
    ],

    'health_record_type' => [
        'vaccination' => 'Impfung',
        'deworming' => 'Entwurmung',
        'vet_visit' => 'Tierarztbesuch',
        'farrier' => 'Hufschmied',
        'dentist' => 'Zahnarzt',
        'check_up' => 'Kontrolluntersuchung',
        'medication' => 'Medikamente',
        'other' => 'Sonstiges',
    ],

    'horse_document_kind' => [
        'passport' => 'Pferdepass',
        'contract' => 'Pensionsvertrag',
        'insurance' => 'Versicherung / Police',
        'vaccine_book' => 'Impfpass',
        'ownership_proof' => 'Eigentumsnachweis',
        'competition_licence' => 'Turnierlizenz',
        'vet_certificate' => 'Tierärztliche Bescheinigung',
        'other' => 'Sonstiges',
    ],

    'invoice_kind' => [
        'fv' => 'Mehrwertsteuerrechnung',
        'fv_proforma' => 'Proforma-Rechnung',
        'fv_korekta' => 'Korrekturrechnung',
    ],

    'invoice_status' => [
        'draft' => 'Entwurf',
        'issued' => 'Ausgestellt',
        'paid' => 'Bezahlt',
        'overdue' => 'Überfällig',
        'void' => 'Storniert',
        'cancelled' => 'Korrigiert',
    ],

    'pass_status' => [
        'active' => 'Aktiv',
        'exhausted' => 'Aufgebraucht',
        'expired' => 'Abgelaufen',
        'cancelled' => 'Storniert',
    ],

    'payment_provider' => [
        'none' => 'Keiner (Offline-Zahlung)',
        'stub' => 'Test (Entwickler)',
        'p24' => 'Przelewy24',
        'payu' => 'PayU',
        'stripe' => 'Stripe',
        'mollie' => 'Mollie',
    ],

    'payment_status' => [
        'pending' => 'Ausstehend',
        'processing' => 'In Bearbeitung',
        'succeeded' => 'Bezahlt',
        'failed' => 'Fehlgeschlagen',
        'refunded' => 'Erstattet',
    ],

    'recurrence_pattern' => [
        'daily' => 'Täglich',
        'weekly' => 'Wöchentlich',
        'monthly' => 'Monatlich',
    ],

    'stable_activity_type' => [
        'feeding' => 'Fütterung',
        'grooming' => 'Putzen / Pflege',
        'turnout' => 'Auslauf auf Koppel',
        'exercise' => 'Arbeit mit dem Pferd',
        'box_cleaning' => 'Boxenreinigung',
        'transport_event' => 'Transport / Event',
        'other' => 'Sonstiges',
    ],

    'feeding_meal' => [
        'breakfast' => 'Morgens',
        'midday' => 'Mittags',
        'evening' => 'Abends',
        'night' => 'Nachts',
    ],
];
