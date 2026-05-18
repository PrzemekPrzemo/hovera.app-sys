<?php

declare(strict_types=1);

return [
    'section' => [
        'title' => 'KSeF (facturation électronique polonaise)',
        'description' => 'Intégration KSeF pour les factures de transport — émises par vous.',
        'disclaimer' => 'Vous obtenez votre jeton KSeF dans votre propre compte KSeF (mf.gov.pl). '
            .'Hovera ne fait que transmettre vos factures — c\'est VOTRE jeton, VOTRE NIP, '
            .'VOTRE responsabilité comptable. Hovera n\'est pas partie à vos contrats de transport '
            .'ni émetteur de vos factures (voir docs/TRANSPORT.md §12).',
        'invoice_title' => 'KSeF — statut d\'envoi',
        'invoice_description' => 'Informations sur la soumission à KSeF (si activée).',
    ],

    'form' => [
        'label' => [
            'nip' => 'NIP de l\'émetteur (le vôtre)',
            'environment' => 'Environnement KSeF',
            'token' => 'Jeton d\'autorisation KSeF',
            'enabled' => 'Activer l\'intégration KSeF',
            'invoice_status' => 'Statut KSeF',
            'reference_number' => 'Numéro de référence KSeF',
            'submitted_at' => 'Envoyé le',
        ],
        'helper' => [
            'nip' => 'NIP polonais à 10 chiffres.',
            'token_empty' => 'Collez le jeton généré dans le panneau MF. Stocké chiffré.',
            'token_set' => 'Jeton enregistré. Saisir une nouvelle valeur pour le remplacer.',
            'enabled' => 'Une fois activé, l\'action « Envoyer à KSeF » apparaît.',
        ],
        'option' => [
            'environment' => [
                'test' => 'Test (ksef-test.mf.gov.pl)',
                'demo' => 'Démo (ksef-demo.mf.gov.pl)',
                'production' => 'Production (ksef.mf.gov.pl)',
            ],
        ],
    ],

    'action' => [
        'submit' => 'Envoyer à KSeF',
        'submit_confirm' => 'Soumettre cette facture à KSeF ? Action irréversible.',
        'submit_bulk' => 'Envoyer la sélection à KSeF',
        'submit_bulk_confirm' => 'Soumettre les factures sélectionnées (max 50) à KSeF ?',
        'refresh' => 'Actualiser le statut KSeF',
        'test_connection' => 'Tester la connexion KSeF',
    ],

    'notify' => [
        'submitted' => 'Facture envoyée à KSeF.',
        'submit_failed' => 'Échec de l\'envoi à KSeF.',
        'status_refreshed' => 'Statut KSeF actualisé.',
        'not_configured' => 'KSeF n\'est pas configuré.',
        'unknown_error' => 'Erreur KSeF inconnue.',
        'test_ok' => 'Connexion KSeF opérationnelle.',
        'test_failed' => 'Échec de la connexion KSeF.',
        'bulk_done' => 'Envoi groupé terminé.',
        'bulk_done_body' => 'Réussis : :ok. Erreurs : :fail.',
    ],

    'status' => [
        'not_submitted' => 'Non envoyé',
        'submitted' => 'Envoyé',
        'accepted' => 'Accepté',
        'rejected' => 'Rejeté',
        'error' => 'Erreur',
    ],

    'table' => [
        'column' => [
            'status' => 'KSeF',
        ],
    ],
];
