<?php

declare(strict_types=1);

return [
    'navigation' => 'Preise & Tarife',
    'title' => 'Transportpreise & Tarife',

    'section' => [
        'rates' => 'Kilometertarife',
        'rates_description' => 'Grundtarife zur Berechnung von Angeboten.',
        'fuel' => 'Kraftstoff',
        'fuel_description' => 'Kraftstoffzuschlag: Übersteigt der aktuelle Dieselpreis den Basispreis, addieren wir die Differenz × Verbrauch.',
        'tax_currency' => 'Steuern & Währung',
        'routing' => 'Karten- und Routinganbieter',
        'routing_description' => 'OpenRouteService (kostenlos) deckt 95% der Fälle ab. Google und Mapbox erfordern einen eigenen API-Schlüssel.',
        'payments' => 'Zahlungen (direct charge)',
        'payments_description' => 'Standard-Zahlungs-URL und Zahlungshinweise — werden bei jedem neuen Angebot automatisch übernommen.',
        'payments_disclaimer' => 'Hovera nimmt KEINE Zahlungen entgegen. Der Kunde zahlt direkt an Sie — Hovera zeigt lediglich die hier eingegebenen Informationen auf der Angebotsseite an. Stripe / Przelewy24 / andere — vollständig Ihre Verantwortung, Ihr Konto, Ihre Steuerabrechnung.',
    ],

    'form' => [
        'label' => [
            'rate_per_km' => 'Tarif pro km',
            'rate_per_km_loaded' => 'Tarif pro km beladen',
            'minimum_charge' => 'Mindestpreis pro Auftrag',
            'fuel_consumption_l_per_100km' => 'Verbrauch (L/100 km)',
            'fuel_surcharge_enabled' => 'Kraftstoffzuschlag aktivieren',
            'fuel_base_price_pln' => 'Basis-Dieselpreis',
            'vat_rate' => 'MwSt.-Satz',
            'currency' => 'Währung',
            'routing_provider' => 'Routinganbieter',
            'routing_api_key' => 'API-Schlüssel',
            'default_payment_url_template' => 'Standard-URL-Vorlage für Zahlung',
            'default_payment_method_label' => 'Standard-Bezeichnung der Zahlungsmethode',
            'payment_instructions' => 'Zahlungshinweise (Fallback)',
        ],
        'helper' => [
            'rate_per_km_loaded' => 'Leer lassen, wenn identisch mit unbeladen.',
            'fuel_surcharge_enabled' => 'Wir addieren die Differenz zwischen aktuellem und Basispreis.',
            'routing_api_key' => 'API-Schlüssel für den gewählten Anbieter. Sicher in der Datenbank gespeichert.',
            'default_payment_url_template' => 'Ihre Zahlungs-URL. Platzhalter: {quote_number}, {gross_total_pln}, {customer_name}.',
            'default_payment_method_label' => 'Z. B. „Stripe", „Przelewy24", „BLIK / Überweisung" — wird unter dem Zahlen-Button angezeigt.',
            'payment_instructions' => 'Text, der auf der Angebotsseite erscheint, wenn keine Zahlungs-URL gesetzt ist (z. B. Banküberweisungsdaten).',
        ],
        'option' => [
            'routing_provider' => [
                'ors' => 'OpenRouteService (kostenlos)',
                'mapbox' => 'Mapbox (eigener Schlüssel)',
                'google' => 'Google Maps Routes (eigener Schlüssel)',
            ],
        ],
    ],

    'action' => [
        'save' => 'Einstellungen speichern',
        'saved' => 'Einstellungen gespeichert.',
    ],
];
