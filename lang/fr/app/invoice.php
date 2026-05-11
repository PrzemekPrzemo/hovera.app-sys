<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'invoice_data' => 'Informations de la facture',
            'buyer' => 'Acheteur',
            'seller' => 'Vendeur (snapshot)',
            'dates' => 'Dates',
            'items' => 'Lignes',
            'notes' => 'Notes',
        ],
        'label' => [
            'kind' => 'Type',
            'number' => 'Numéro',
            'number_placeholder' => '— attribué lors de l’émission —',
            'status' => 'Statut',
            'client' => 'Client',
            'buyer_name' => 'Raison sociale / nom et prénom',
            'buyer_nip' => 'NIP (optionnel pour les particuliers)',
            'buyer_address' => 'Adresse',
            'buyer_postal_code' => 'Code postal',
            'buyer_city' => 'Ville',
            'buyer_country' => 'Pays',
            'seller_name' => 'Nom',
            'seller_nip' => 'NIP',
            'seller_address' => 'Adresse',
            'seller_postal_code' => 'Code postal',
            'seller_city' => 'Ville',
            'seller_country' => 'Pays',
            'issued_at' => 'Émise le',
            'sale_date' => 'Date de vente',
            'due_at' => 'Échéance',
            'item_name' => 'Désignation',
            'item_quantity' => 'Quantité',
            'item_unit' => 'Unité',
            'item_unit_price' => 'Prix unitaire HT',
            'item_vat' => 'TVA',
            'notes_label' => 'Remarques',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Numéro',
            'kind' => 'Type',
            'issued_at' => 'Émise le',
            'client' => 'Acheteur',
            'total' => 'TTC',
            'status' => 'Statut',
            'due_at' => 'Échéance',
        ],
        'filter' => [
            'overdue' => 'En retard',
        ],
    ],

    'action' => [
        'issue' => [
            'label' => 'Émettre',
            'success' => 'Facture émise',
            'failure_title' => 'Impossible d’émettre la facture',
        ],
        'correct' => [
            'label' => 'Rectification',
            'success_title' => 'Rectification créée',
            'success_body' => 'Ouvrez le brouillon :id et modifiez les lignes.',
            'failure_title' => 'Erreur',
        ],
        'ksef' => [
            'label' => 'Envoyer à KSeF',
            'modal_description' => 'La facture sera signée avec le certificat de l’écurie puis envoyée à KSeF.',
            'auth_success_title' => 'KSeF : authentification réussie',
            'auth_success_body' => 'L’envoi du contenu de la facture est en préparation (PR 4b).',
            'failure_title' => 'KSeF : erreur',
        ],
        'email' => [
            'label' => 'Envoyer par e-mail',
            'modal_description' => 'Nous enverrons un lien vers la facture à l’e-mail du client. Le lien reste actif pendant 90 jours (ou 14 jours après l’échéance).',
            'no_email' => 'Aucune adresse e-mail pour le client',
            'success' => 'Facture envoyée par e-mail au client',
        ],
    ],
];
