<?php

declare(strict_types=1);

return [
    'title' => ':horse — :tenant',
    'back' => '← Retour au tableau de bord',

    'info' => [
        'breed' => 'Race',
        'sex' => 'Sexe',
        'color' => 'Robe',
        'age' => 'Âge',
        'age_value' => ':years ans (:year)',
        'microchip' => 'Puce électronique',
        'passport' => 'Passeport',
    ],

    'sections' => [
        'boarding' => 'Pension et coûts',
        'feeding_plan' => 'Plan d’alimentation',
        'photos' => 'Galerie de photos',
        'activities' => 'Ce que nous faisons avec votre cheval',
        'messages' => 'Messages avec l’écurie',
        'documents' => 'Documents',
        'health' => 'Historique vétérinaire',
    ],

    'feeding_plan' => [
        'disclaimer' => 'Le plan est défini par l’écurie. Toute modification d’alimentation doit être discutée par e-mail ou via la section « Messages ».',
    ],

    'box' => [
        'pill' => '🏠 Box :label',
        'monthly_suffix' => '/mois',
        'monthly_label' => 'pension : :rate',
    ],

    'services' => [
        'heading' => 'Prestations facturées',
        'col_item' => 'Prestation',
        'col_price' => 'Prix',
        'col_frequency' => 'Fréquence',
        'col_monthly' => '~mois',
        'price_per_unit' => ':amount zł / :unit',
    ],

    'cost' => [
        'monthly_label' => 'Coût mensuel estimé :',
        'monthly_disclaimer' => 'Hors prestations « à l’utilisation » et ponctuelles — celles-ci n’apparaissent que lorsqu’elles sont effectivement facturées.',
    ],

    'messages' => [
        'sent_flash' => '✓ Message envoyé — l’écurie a reçu une notification par e-mail.',
        'subject_placeholder' => 'Objet (optionnel)',
        'body_placeholder' => 'Écrivez un message à l’écurie…',
        'send' => 'Envoyer',
        'you' => 'Vous',
        'empty' => 'Aucun message — envoyez le premier.',
        'attachment_fallback' => 'pièce jointe',
    ],

    'documents' => [
        'uploaded_flash' => '✓ Document téléversé.',
        'deleted_flash' => '✓ Document supprimé.',
        'name_placeholder' => 'Nom du document',
        'description_placeholder' => 'Description (optionnelle)',
        'upload' => 'Téléverser un document',
        'uploaded_by_stable' => 'Écurie',
        'uploaded_by_you' => 'Vous',
        'valid_until' => 'valide jusqu’au :',
        'download' => '📥 Télécharger',
        'delete' => 'Supprimer',
        'delete_confirm' => 'Supprimer ce document ?',
        'empty' => 'Aucun document. Téléversez le premier.',
    ],

    'health' => [
        'performed_by_label' => 'Effectué par : :name',
        'next_due_label' => 'Prochain soin : :date',
        'overdue_pill' => 'En retard',
        'soon_pill' => 'Bientôt',
        'empty' => 'Aucune entrée vétérinaire.',
    ],
];
