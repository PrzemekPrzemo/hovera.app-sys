<?php

declare(strict_types=1);

return [
    'tenant_type' => [
        'stable' => 'Stall',
        'transporter' => 'Transportunternehmen',
    ],

    'quote_status' => [
        'draft' => 'Entwurf',
        'sent' => 'Versendet',
        'accepted' => 'Angenommen',
        'rejected' => 'Abgelehnt',
        'expired' => 'Abgelaufen',
        'withdrawn' => 'Zurückgezogen',
    ],

    'verification_status' => [
        'pending' => 'Dokumente ausstehend',
        'under_review' => 'In Prüfung',
        'verified' => 'Verifiziert',
        'rejected' => 'Abgelehnt',
    ],

    'transport_lead_status' => [
        'open' => 'Offen',
        'quoted' => 'Angeboten',
        'accepted' => 'Angenommen',
        'rejected' => 'Abgelehnt',
        'expired' => 'Abgelaufen',
        'cancelled' => 'Storniert',
    ],

    'transport_invoice_kind' => [
        'fv' => 'Rechnung (USt)',
        'fv_proforma' => 'Pro-forma-Rechnung',
        'fv_korekta' => 'Korrekturrechnung',
    ],

    'transport_invoice_status' => [
        'draft' => 'Entwurf',
        'issued' => 'Ausgestellt',
        'paid' => 'Bezahlt',
        'overdue' => 'Überfällig',
        'void' => 'Storniert',
        'cancelled' => 'Annulliert',
    ],

    'transporter_document_type' => [
        'company_registration' => 'Handelsregister',
        'company_registration_description' => 'Handelsregisterauszug (PDF/JPG).',
        'animal_transport_cert' => 'Tiertransport-Zertifikat',
        'animal_transport_cert_description' => 'EU-VO 1/2005 — Pflicht für Pferdetransporte.',
        'insurance_ocp' => 'Frachtführer-Haftpflicht',
        'insurance_ocp_description' => 'Haftpflichtversicherung des Frachtführers.',
        'insurance_ocs' => 'Transportversicherung (Ladung)',
        'insurance_ocs_description' => 'Versicherung gegen Schäden am transportierten Tier.',
        'vehicle_registration' => 'Fahrzeugschein',
        'vehicle_registration_description' => 'Scan des Fahrzeugscheins — wir prüfen das nächste HU-Datum.',
        'other' => 'Sonstiges Dokument',
        'other_description' => 'Z. B. Gemeinschaftslizenz, Bescheinigung der Gewerbeaufsicht.',
    ],

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
