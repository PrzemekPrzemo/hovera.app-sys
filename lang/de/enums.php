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
        // @todo native review — machine-translated from PL/EN.
        'company_registration' => 'Handelsregister',
        'company_registration_description' => 'Handelsregisterauszug (KRS / CEIDG in PL, äquivalent anderswo — PDF/JPG).',

        // Legacy — veraltet, im neuen UI ausgeblendet, aus Rückwärtskompatibilität erhalten.
        'animal_transport_cert' => 'Tiertransport-Zertifikat (legacy)',
        'animal_transport_cert_description' => 'Altes EU-VO 1/2005 — ersetzt durch das PWL-Zulassungszertifikat des Transportmittels.',
        'insurance_ocp' => 'Frachtführer-Haftpflicht (legacy)',
        'insurance_ocp_description' => 'Ersetzt durch den neuen Eintrag „Frachtführer-Haftpflicht" in der PWL-Liste.',
        'insurance_ocs' => 'Transportversicherung (Ladung)',
        'insurance_ocs_description' => 'Versicherung gegen Schäden am transportierten Tier. Optional, aber empfohlen.',
        'vehicle_registration' => 'Fahrzeugschein (legacy)',
        'vehicle_registration_description' => 'Ersetzt durch das PWL-Zulassungszertifikat des Transportmittels.',

        // PWL — polnisches Regime für innergemeinschaftliche Lebendtiertransporte.
        // @todo native review — DE-Übersetzung der polnischen Regulierungstexte.
        'road_carrier_license' => 'Genehmigung zur Ausübung des Berufs Kraftverkehrsunternehmer',
        'road_carrier_license_description' => 'Ausgestellt von GITD oder Starosta gemäß EU-VO 1071/2009 + polnischem Straßentransportgesetz von 2001.',
        'pwl_authorization_type1' => 'PWL-Frachtführergenehmigung — Typ 1 (< 8h)',
        'pwl_authorization_type1_description' => 'Genehmigung des PIW (Veterinäramt) für Transporte bis 8 Stunden. Typ 1 wählen, falls nur Kurztransporte.',
        'pwl_authorization_type2' => 'PWL-Frachtführergenehmigung — Typ 2 (> 8h)',
        'pwl_authorization_type2_description' => 'PIW-Genehmigung für Langtransporte über 8 Stunden. Deckt auch Typ-1-Anwendungen ab.',
        'pwl_driver_handler_certificate' => 'PWL-Kompetenznachweis für Fahrer und Tierbetreuer',
        'pwl_driver_handler_certificate_description' => 'Artikel 6 EU-VO 1/2005 — Kompetenznachweis für Fahrer und Tierbetreuer. Bitte alle Teammitglieder hochladen.',
        'pwl_vehicle_approval_certificate' => 'PWL-Zulassungszertifikat des Transportmittels',
        'pwl_vehicle_approval_certificate_description' => 'EU-VO 1/2005 Art. 18 (< 8h) oder Art. 19 (> 8h). Pflicht für jedes Pferdetransportfahrzeug.',
        'wash_disinfection_log' => 'Wasch- und Desinfektionsbuch des Transportmittels',
        'wash_disinfection_log_description' => 'Pflicht laut polnischem Tiergesundheitsschutzgesetz von 2004. Aktuelle Einträge der letzten 12 Monate hochladen.',
        'carrier_liability_insurance' => 'Frachtführer-Haftpflichtversicherung',
        'carrier_liability_insurance_description' => 'Straßenfrachtführer-Haftpflichtpolice. Wir prüfen Ablaufdatum und Deckungssumme.',

        'other' => 'Sonstiges Dokument',
        'other_description' => 'Z. B. Gemeinschaftslizenz, Bescheinigung der Gewerbeaufsicht, Cargo-Zusatzpolice.',
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
