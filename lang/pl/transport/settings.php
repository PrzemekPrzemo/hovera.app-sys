<?php

declare(strict_types=1);

return [
    'navigation' => 'Cennik i stawki',
    'title' => 'Cennik i stawki transportu',

    'section' => [
        'rates' => 'Stawki za kilometr',
        'rates_description' => 'Stawki podstawowe — używane do wyliczania ofert.',
        'fuel' => 'Paliwo',
        'fuel_description' => 'Dopłata paliwowa: gdy aktualna cena ON przekracza cenę bazową, system dolicza różnicę × zużycie.',
        'tax_currency' => 'Podatki i waluta',
        'routing' => 'Dostawca map i tras',
        'routing_description' => 'OpenRouteService (darmowy) wystarcza w 95% przypadków. Google i Mapbox wymagają własnego klucza API.',
        'payments' => 'Płatności (direct charge)',
        'payments_description' => 'Domyślny link do bramki i instrukcje płatności — wstawiane automatycznie do każdej nowej oferty.',
        'payments_disclaimer' => 'Hovera NIE pośredniczy w płatnościach. Klient płaci bezpośrednio do Ciebie — Hovera tylko wyświetla wprowadzone tu informacje na stronie akceptacji oferty. Stripe / Przelewy24 / inne — w pełni Twoja odpowiedzialność, Twoje konto, Twoje rozliczenie podatkowe.',
    ],

    'form' => [
        'label' => [
            'rate_per_km' => 'Stawka za km',
            'rate_per_km_loaded' => 'Stawka za km z koniem',
            'minimum_charge' => 'Minimalna opłata zlecenia',
            'extra_horse_fee_default' => 'Dopłata za dodatkowego konia',
            'fixed_fees_default' => 'Domyślne stałe opłaty',
            'fixed_fees_name' => 'Nazwa',
            'fixed_fees_amount' => 'Kwota',
            'surcharge_percent_default' => 'Domyślna marża %',
            'fuel_consumption_l_per_100km' => 'Spalanie (L/100 km)',
            'fuel_surcharge_enabled' => 'Włącz dopłatę paliwową',
            'fuel_base_price_pln' => 'Cena bazowa ON',
            'vat_rate' => 'Stawka VAT',
            'currency' => 'Waluta',
            'routing_provider' => 'Dostawca tras',
            'routing_api_key' => 'Klucz API',
            'default_payment_url_template' => 'Domyślny szablon URL płatności',
            'default_payment_method_label' => 'Domyślna etykieta metody płatności',
            'payment_instructions' => 'Instrukcje płatności (fallback)',
        ],
        'helper' => [
            'rate_per_km_loaded' => 'Pozostaw puste jeśli taka sama jak bez koni.',
            'extra_horse_fee_default' => 'Nalicza się od drugiego konia w górę. 0 = brak doliczenia (kalkulator nie różnicuje cen).',
            'fixed_fees_default' => 'Pre-fill dla nowych wycen (autostrady, prom, etc.). Każda pozycja zostanie automatycznie dodana — user może usunąć/edytować per wycena.',
            'surcharge_percent_default' => 'Marża doliczana procentowo do sumy kosztów (po dopasowaniu do opłaty minimalnej, przed VAT). Wartości typowe: 10–25%. Puste = brak marży.',
            'fuel_surcharge_enabled' => 'Doliczamy różnicę pomiędzy ceną aktualną a bazową.',
            'routing_api_key' => 'Klucz API dla wybranego dostawcy. Przechowujemy bezpiecznie w bazie.',
            'default_payment_url_template' => 'Twój link do bramki — wspierane placeholdery: {quote_number}, {gross_total_pln}, {customer_name}. Auto-wstawiany do nowej oferty (możesz nadpisać per oferta).',
            'default_payment_method_label' => 'Np. „Stripe", „Przelewy24", „BLIK / przelew" — pokazane pod przyciskiem „Zapłać" na stronie oferty.',
            'payment_instructions' => 'Tekst widoczny na stronie oferty, gdy nie podasz linku do bramki. Np. dane do przelewu: bank, numer konta, tytuł przelewu.',
        ],
        'option' => [
            'routing_provider' => [
                'ors' => 'OpenRouteService (darmowy)',
                'mapbox' => 'Mapbox (własny klucz)',
                'google' => 'Google Maps Routes (własny klucz)',
            ],
        ],
        'action' => [
            'add_fixed_fee' => 'Dodaj opłatę',
        ],
    ],

    'action' => [
        'save' => 'Zapisz ustawienia',
        'saved' => 'Ustawienia zapisane.',
    ],
];
