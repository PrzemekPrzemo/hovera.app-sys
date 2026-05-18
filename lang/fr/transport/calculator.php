<?php

declare(strict_types=1);

return [
    'navigation' => 'Calculateur de devis',
    'title' => 'Calculateur de devis de transport',

    'section' => [
        'route' => 'Itinéraire',
        'options' => 'Options',
    ],

    'form' => [
        'label' => [
            'from_address' => 'Adresse de prise en charge',
            'to_address' => 'Adresse de livraison',
            'loaded' => 'Chargé (avec cheval)',
            'round_trip' => 'Aller-retour',
            'avoid_tolls' => 'Éviter les péages',
            'avoid_ferries' => 'Éviter les ferries',
            'profile' => 'Profil du véhicule',
        ],
        'placeholder' => [
            'from_address' => 'p. ex. Écurie, Rue Principale 1, Paris',
            'to_address' => 'p. ex. Lyon, Av. Sport 1',
        ],
        'option' => [
            'profile' => [
                'truck' => 'Camion (PL)',
                'car' => 'Voiture',
            ],
        ],
    ],

    'action' => [
        'submit' => 'Calculer le devis',
        'calculated' => 'Devis calculé.',
        'failed' => 'Échec du calcul du devis',
        'save_as_quote' => 'Enregistrer comme devis',
    ],

    'result' => [
        'heading' => 'Résultat du devis',
        'from' => 'De',
        'to' => 'Vers',
        'distance' => 'Distance',
        'duration' => 'Durée du trajet',
        'rate_used' => 'Tarif appliqué',
        'base_cost' => 'Coût de base',
        'fuel_surcharge' => 'Surcoût carburant',
        'minimum_adjustment' => 'Ajustement au tarif minimum',
        'net_total' => 'Total HT',
        'vat' => 'TVA (:rate%)',
        'gross_total' => 'Total TTC',
        'routing_via' => 'Itinéraire calculé via : :provider',
    ],

    'error' => [
        'no_tenant' => 'Aucun tenant actif — veuillez vous reconnecter.',
        'unknown' => 'Erreur inattendue. Veuillez réessayer.',
    ],
];
