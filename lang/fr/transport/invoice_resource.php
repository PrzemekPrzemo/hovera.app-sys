<?php

declare(strict_types=1);

return [
    'navigation' => 'Factures',

    'section' => [
        'header' => 'En-tête',
        'parties' => 'Parties',
        'amounts' => 'Montants',
        'dates' => 'Dates',
        'route' => 'Itinéraire',
        'notes' => 'Notes',
        'correction' => 'Correction de facture',
        'correction_help' => 'Sélectionnez la facture originale corrigée par cette KOR.',
    ],

    'form' => [
        'label' => [
            'seller' => 'Vendeur',
            'buyer' => 'Acheteur',
            'net_total' => 'HT',
            'vat_total' => 'TVA',
            'gross_total' => 'TTC',
            'corrects_invoice' => 'Facture corrigée',
        ],
        'helper' => [
            'kind' => 'Choisissez « Korekta » si cette facture corrige une facture précédente.',
            'corrects_invoice' => 'Numéro de la facture originale. Apparaît dans le bloc XML `<DaneFaKorygowanej>`.',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Numéro',
            'kind' => 'Type',
            'buyer' => 'Acheteur',
            'issued_at' => 'Émise',
            'due_at' => 'Échéance',
            'total' => 'TTC',
            'status' => 'Statut',
        ],
    ],

    'action' => [
        'download_pdf' => 'Télécharger le PDF',
        'send_email' => 'Envoyer par e-mail',
        'mark_paid' => 'Marquer payée',
    ],

    'notify' => [
        'sent' => 'Facture envoyée',
        'sent_body' => 'Facture :number envoyée à :email avec PDF en pièce jointe.',
        'no_buyer_email' => 'L’acheteur n’a pas d’e-mail — téléchargez le PDF et envoyez manuellement.',
        'email_failed' => 'Échec de l’envoi',
        'marked_paid' => 'Facture marquée comme payée',
    ],
];
