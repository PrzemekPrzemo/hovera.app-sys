<?php

declare(strict_types=1);

return [
    'navigation' => 'Pricing & rates',
    'title' => 'Transport pricing & rates',

    'section' => [
        'rates' => 'Per-kilometre rates',
        'rates_description' => 'Base rates used to calculate quotes.',
        'fuel' => 'Fuel',
        'fuel_description' => 'Fuel surcharge: when the current diesel price exceeds the base, we add the difference × consumption.',
        'tax_currency' => 'Tax & currency',
        'routing' => 'Maps & routing provider',
        'routing_description' => 'OpenRouteService (free) covers 95% of cases. Google and Mapbox require your own API key.',
        'payments' => 'Payments (direct charge)',
        'payments_description' => 'Default payment gateway URL and payment instructions — auto-filled on every new quote.',
        'payments_disclaimer' => 'Hovera does NOT process payments. The customer pays you directly — Hovera only displays the information you enter here on the quote acceptance page. Stripe / Przelewy24 / other — fully your responsibility, your account, your tax filings.',
    ],

    'form' => [
        'label' => [
            'rate_per_km' => 'Rate per km',
            'rate_per_km_loaded' => 'Rate per km loaded',
            'minimum_charge' => 'Minimum charge per job',
            'extra_horse_fee_default' => 'Surcharge per extra horse',
            'fixed_fees_default' => 'Default fixed fees',
            'fixed_fees_name' => 'Name',
            'fixed_fees_amount' => 'Amount',
            'surcharge_percent_default' => 'Default margin %',
            'fuel_consumption_l_per_100km' => 'Fuel consumption (L/100 km)',
            'fuel_surcharge_enabled' => 'Enable fuel surcharge',
            'fuel_calculation_mode' => 'Fuel calculation mode',
            'fuel_base_price_pln' => 'Base diesel price',
            'vat_rate' => 'VAT rate',
            'currency' => 'Currency',
            'routing_provider' => 'Routing provider',
            'routing_api_key' => 'API key',
            'default_payment_url_template' => 'Default payment URL template',
            'default_payment_method_label' => 'Default payment method label',
            'payment_instructions' => 'Payment instructions (fallback)',
        ],
        'helper' => [
            'rate_per_km_loaded' => 'Leave empty if the same as unloaded.',
            'extra_horse_fee_default' => 'Applies from the second horse onwards. 0 = disabled (the calculator returns the same price regardless of horse count).',
            'fixed_fees_default' => 'Pre-fill for new quotes (tolls, ferry, etc.). Each item is added automatically — user can remove/edit per quote.',
            'surcharge_percent_default' => 'Percent margin added to costs (after minimum-charge adjustment, before VAT). Typical values: 10–25%. Empty = no margin.',
            'fuel_surcharge_enabled' => 'Add fuel to the quote (mode determines the method).',
            'fuel_calculation_mode' => '"Surcharge" — only the difference over the base price (rate_per_km already accounts for base fuel). "Full cost" — full current price × consumption × distance (rate_per_km then covers only labour + margin).',
            'fuel_base_price_pln' => 'Base diesel price used in "Surcharge" mode — surcharge calculated from the difference. In "Full cost" mode the field is unused.',
            'routing_api_key' => 'API key for the selected provider. Stored securely in the database.',
            'default_payment_url_template' => 'Your payment gateway URL. Supported placeholders: {quote_number}, {gross_total_pln}, {customer_name}. Auto-applied to new quotes (you can override per-quote).',
            'default_payment_method_label' => 'E.g. "Stripe", "Przelewy24", "BLIK / transfer" — shown below the Pay button on the quote page.',
            'payment_instructions' => 'Text shown on the quote page when no payment URL is set. E.g. bank transfer details: bank, account, transfer title.',
        ],
        'option' => [
            'routing_provider' => [
                'ors' => 'OpenRouteService (free)',
                'mapbox' => 'Mapbox (your key)',
                'google' => 'Google Maps Routes (your key)',
            ],
        ],
        'action' => [
            'add_fixed_fee' => 'Add fee',
        ],
    ],

    'action' => [
        'save' => 'Save settings',
        'saved' => 'Settings saved.',
    ],
];
