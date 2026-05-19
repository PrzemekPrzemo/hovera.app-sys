<?php

declare(strict_types=1);

return [
    'section' => [
        'title' => 'Przelewy24 — lien automatique sur les devis',
        'description' => 'Génération automatique d\'un lien P24 (BLIK / virement / carte) pour '
            .'chaque nouveau devis de transport. Le client paie directement sur votre compte P24.',
        'disclaimer' => 'Przelewy24 est VOTRE compte, VOTRE contrat avec DialCom24, VOS factures. '
            .'Hovera ne fait que rediriger techniquement le client vers votre checkout — tous les '
            .'fonds arrivent directement sur votre compte P24 (Hovera n\'est pas un intermédiaire '
            .'de paiement pour le transport — voir docs/TRANSPORT.md §12 et §15.5).',
    ],

    'form' => [
        'label' => [
            'autopay_enabled' => 'Générer automatiquement un lien P24 pour les nouveaux devis',
        ],
        'helper' => [
            'autopay_enabled' => 'Lorsque activé, la création d\'un devis en PLN enregistre '
                .'automatiquement une session P24 et stocke le lien comme payment_url. Le client '
                .'verra un bouton "Payer avec Przelewy24" sur la page publique du devis.',
            'credentials_pointer' => 'Configurez merchant_id / pos_id / crc / api_key dans la page '
                .'"Paramètres de paiement" (/app/payment-settings). Un seul formulaire couvre '
                .'toutes les intégrations P24 (réservations, devis).',
        ],
    ],

    'notify' => [
        'autopay_failed' => 'Impossible de générer le lien Przelewy24',
    ],

    'return' => [
        'paid' => 'Le paiement pour le devis {number} a été reçu — merci !',
        'pending' => 'Le paiement pour le devis {number} est en cours de vérification. '
            .'Veuillez actualiser la page dans un instant.',
        'unknown' => 'Devis introuvable. Veuillez contacter le transporteur.',
    ],
];
