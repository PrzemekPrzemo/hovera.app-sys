<?php

declare(strict_types=1);

return [
    'directions' => [
        'from_stable' => 'Écurie → Client',
        'from_client' => 'Client → Écurie',
    ],

    'form' => [
        'label' => [
            'subject' => 'Objet (optionnel)',
            'body' => 'Contenu',
            'attachments' => 'Pièces jointes (max 5, jusqu’à 10 Mo chacune)',
        ],
    ],

    'table' => [
        'column' => [
            'sent_at' => 'Envoyée le',
            'direction' => 'Sens',
            'subject' => 'Objet',
            'body' => 'Aperçu',
            'attachments_short' => 'P. j.',
            'read_short' => 'Lue',
        ],
    ],

    'action' => [
        'create' => [
            'label' => 'Écrire au propriétaire',
            'failed' => 'L’envoi a échoué',
            'sent' => 'Message envoyé',
        ],
        'mark_read' => [
            'label' => 'Marquer comme lue',
            'success' => 'Marquée comme lue',
        ],
    ],
];
