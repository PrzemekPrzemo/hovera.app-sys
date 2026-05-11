<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'numbering' => 'Numérotation des factures',
            'numbering_description' => 'Placeholders : {seq}, {seq:NN} (par exemple {seq:4} → 0001), {YYYY}, {YY}, {MM}, {M}, {DD}, {prefix}.',
            'seller' => 'Coordonnées du vendeur (snapshot sur les factures)',
            'seller_description' => 'Ces informations sont enregistrées sur chaque nouvelle facture au moment de sa création. Modifier les informations de l’écurie n’affectera pas les factures déjà émises.',
        ],
        'label' => [
            'template_fv' => 'Modèle FV',
            'template_pro' => 'Modèle pro forma',
            'template_kor' => 'Modèle rectificatif',
            'prefix' => 'Préfixe (placeholder {prefix})',
            'prefix_placeholder' => 'par exemple STW',
            'reset_interval' => 'Réinitialisation de la numérotation',
            'default_due_days' => 'Délai de paiement par défaut (jours)',
            'seller_name' => 'Nom du vendeur',
            'seller_nip' => 'NIP du vendeur',
            'seller_address' => 'Adresse',
            'seller_postal_code' => 'Code postal',
            'seller_city' => 'Ville',
        ],
    ],

    'action' => [
        'saved' => 'Paramètres de facturation enregistrés',
    ],

    'reset_options' => [
        'yearly' => 'Annuelle (départ à 1 chaque nouvelle année)',
        'monthly' => 'Mensuelle (départ à 1 chaque mois)',
        'never' => 'Jamais (numérotation continue)',
    ],
];
