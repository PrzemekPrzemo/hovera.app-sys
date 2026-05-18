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
        'payments' => 'Paiements (direct charge)',
        'payments_description' => 'URL par défaut de la passerelle de paiement et instructions — pré-remplies sur chaque nouveau devis.',
        'payments_disclaimer' => 'Hovera N’ACCEPTE PAS les paiements. Le client vous paie directement — Hovera affiche uniquement les informations saisies ici sur la page d’acceptation du devis. Stripe / Przelewy24 / autre — votre entière responsabilité, votre compte, votre déclaration fiscale.',
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
            'default_payment_url_template' => 'Modèle d’URL de paiement par défaut',
            'default_payment_method_label' => 'Libellé par défaut du mode de paiement',
            'payment_instructions' => 'Instructions de paiement (repli)',
        ],
        'helper' => [
            'rate_per_km_loaded' => 'Laisser vide si identique à non chargé.',
            'fuel_surcharge_enabled' => 'Nous ajoutons la différence entre le prix actuel et de base.',
            'routing_api_key' => 'Clé API pour le fournisseur choisi. Stockée en toute sécurité.',
            'default_payment_url_template' => 'Votre URL de paiement. Variables : {quote_number}, {gross_total_pln}, {customer_name}.',
            'default_payment_method_label' => 'Ex. : « Stripe », « Przelewy24 », « BLIK / virement » — affiché sous le bouton Payer.',
            'payment_instructions' => 'Texte affiché sur la page du devis lorsque aucune URL de paiement n’est définie (ex. coordonnées bancaires).',
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
