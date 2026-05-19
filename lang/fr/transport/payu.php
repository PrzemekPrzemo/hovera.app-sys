<?php

declare(strict_types=1);

return [
    'section' => [
        'title' => 'PayU — lien automatique sur les devis',
        'description' => 'Génération automatique d\'un lien PayU (BLIK / virement / carte / Apple Pay / '
            .'Google Pay) pour chaque nouveau devis de transport. Le client paie directement sur votre compte PayU.',
        'disclaimer' => 'PayU est VOTRE compte, VOTRE contrat avec PayU.pl S.A., VOS factures. '
            .'Hovera redirige uniquement techniquement le client vers votre paiement — tous les fonds '
            .'arrivent directement sur votre compte PayU (Hovera n\'est pas un intermédiaire de '
            .'paiement pour le transport — voir docs/TRANSPORT.md §12 et §16).',
    ],

    'form' => [
        'label' => [
            'autopay_enabled' => 'Générer automatiquement le lien PayU pour les nouveaux devis',
        ],
        'helper' => [
            'autopay_enabled' => 'Lorsque activé, la création d\'un devis en PLN enregistrera '
                .'automatiquement une commande PayU et stockera le lien comme payment_url. '
                .'Le client verra un bouton "Payer avec PayU" sur la page publique du devis.',
            'credentials_pointer' => 'Configurez pos_id / oauth_client_id / oauth_client_secret '
                .'/ md5_key dans la page "Paramètres de paiement" (/app/payment-settings).',
        ],
    ],

    'notify' => [
        'autopay_failed' => 'Impossible de générer le lien PayU',
    ],

    'return' => [
        'paid' => 'Le paiement du devis {number} a été reçu — merci !',
        'pending' => 'Le paiement du devis {number} est en cours de vérification. Veuillez '
            .'actualiser la page dans un instant.',
        'unknown' => 'Devis introuvable. Veuillez contacter le transporteur.',
    ],
];
