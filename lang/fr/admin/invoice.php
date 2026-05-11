<?php

declare(strict_types=1);

return [
    'navigation' => 'Factures SaaS',
    'model' => 'Facture SaaS',
    'model_plural' => 'Factures SaaS',

    'kind' => [
        'regular' => 'Standard (FV)',
        'proforma' => 'Pro forma',
        'correction' => 'Rectificative',
    ],

    'form' => [
        'section' => [
            'basics' => 'Informations de base',
            'amounts' => 'Montants',
            'dates' => 'Dates',
        ],
        'label' => [
            'tenant' => 'Écurie (acheteur)',
            'number' => 'Numéro de facture',
            'kind' => 'Type',
            'subtotal' => 'HT (centimes)',
            'vat_rate' => 'Taux de TVA (%)',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Numéro',
            'tenant' => 'Écurie',
            'issued_at' => 'Émise le',
            'total' => 'Total TTC',
            'status' => 'Statut',
            'ksef_status' => 'KSeF',
        ],
    ],

    'action' => [
        'issue_manual' => 'Émettre une facture manuellement',
        'send_p24_link' => 'Envoyer un lien P24',
        'p24_link_generated' => 'Lien Przelewy24 généré',
        'p24_link_failed' => 'Impossible de générer le lien P24',
        'send_to_ksef' => 'Envoyer à KSeF',
        'ksef_sent' => 'Envoyée à KSeF',
        'ksef_failed' => 'Échec de l’envoi à KSeF',
        'ksef_reference' => 'Numéro de référence KSeF',
        'download_pdf' => 'Télécharger le PDF',
        'pdf_stub_title' => 'Génération du PDF reportée',
        'pdf_stub_body' => 'La génération complète du PDF de facture nécessite dompdf/snappy — sera ajoutée dans une PR de suivi.',
        'resend_email' => 'Renvoyer l’e-mail',
    ],

    'p24_return' => [
        'paid' => 'Le paiement de la facture :number a été confirmé.',
        'pending' => 'Merci ! Le paiement de la facture :number est en cours de vérification — cela prend généralement quelques minutes.',
        'unknown' => 'Facture non reconnue — vérifiez votre e-mail de confirmation.',
    ],
];
