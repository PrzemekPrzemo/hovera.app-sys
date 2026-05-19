<?php

declare(strict_types=1);

return [
    'navigation' => 'Achats d\'add-ons',
    'model' => 'Achat d\'add-on',
    'model_plural' => 'Achats d\'add-ons',

    'form' => [
        'section' => [
            'basics' => 'Informations de base',
            'status' => 'Statut et paiement',
        ],
        'label' => [
            'tenant' => 'Écurie (tenant)',
            'addon' => 'Add-on (choisir dans le catalogue)',
            'addon_code' => 'Code add-on',
            'addon_name' => 'Nom add-on (instantané)',
            'currency' => 'Devise',
            'amount_cents' => 'Montant (unité minimale)',
            'status' => 'Statut',
            'p24_link' => 'Lien P24 (après génération)',
            'p24_link_none' => '— pas de lien, utilisez l\'action « Générer lien P24 »',
        ],
        'helper' => [
            'amount_cents' => 'Montant dans la plus petite unité (grosze pour PLN, cents pour EUR). '
                .'Pré-rempli depuis la tarification PlanAddon après sélection ci-dessus.',
        ],
    ],

    'status' => [
        'pending' => 'En attente de paiement',
        'paid' => 'Payé',
        'failed' => 'Paiement échoué',
        'cancelled' => 'Annulé',
    ],

    'table' => [
        'column' => [
            'tenant' => 'Écurie',
            'addon' => 'Add-on',
            'amount' => 'Montant',
            'status' => 'Statut',
            'paid_at' => 'Payé le',
            'created_at' => 'Créé le',
        ],
    ],

    'action' => [
        'generate_p24_link' => 'Générer lien P24',
    ],

    'notify' => [
        'link_generated' => 'Lien P24 généré — copiez ci-dessous et envoyez au client',
        'link_failed' => 'Impossible de générer le lien P24',
    ],

    'return' => [
        'paid' => 'Achat add-on « {code} » reçu — merci !',
        'pending' => 'Achat add-on « {code} » en cours de vérification.',
        'unknown' => 'Achat add-on introuvable.',
    ],
];
