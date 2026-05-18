<?php

declare(strict_types=1);

return [
    'navigation' => 'Tarifs',
    'title' => 'Tarifs de transport',

    'section' => [
        'rates' => 'Tarifs au kilomètre',
        'rates_description' => 'Tarifs de base utilisés pour calculer les devis.',
        'fuel' => 'Carburant',
        'fuel_description' => 'Surcoût carburant : lorsque le prix actuel du gazole dépasse le prix de base, nous ajoutons la différence × consommation.',
        'tax_currency' => 'Taxe et devise',
        'routing' => 'Fournisseur de cartes et itinéraires',
        'routing_description' => 'OpenRouteService (gratuit) couvre 95% des cas. Google et Mapbox nécessitent votre propre clé API.',
    ],

    'form' => [
        'label' => [
            'rate_per_km' => 'Tarif au km',
            'rate_per_km_loaded' => 'Tarif au km chargé',
            'minimum_charge' => 'Tarif minimum par mission',
            'fuel_consumption_l_per_100km' => 'Consommation (L/100 km)',
            'fuel_surcharge_enabled' => 'Activer le surcoût carburant',
            'fuel_base_price_pln' => 'Prix de base du gazole',
            'vat_rate' => 'Taux de TVA',
            'currency' => 'Devise',
            'routing_provider' => 'Fournisseur d’itinéraires',
            'routing_api_key' => 'Clé API',
        ],
        'helper' => [
            'rate_per_km_loaded' => 'Laisser vide si identique à non chargé.',
            'fuel_surcharge_enabled' => 'Nous ajoutons la différence entre le prix actuel et de base.',
            'routing_api_key' => 'Clé API pour le fournisseur choisi. Stockée en toute sécurité.',
        ],
        'option' => [
            'routing_provider' => [
                'ors' => 'OpenRouteService (gratuit)',
                'mapbox' => 'Mapbox (votre clé)',
                'google' => 'Google Maps Routes (votre clé)',
            ],
        ],
    ],

    'action' => [
        'save' => 'Enregistrer les paramètres',
        'saved' => 'Paramètres enregistrés.',
    ],
];
