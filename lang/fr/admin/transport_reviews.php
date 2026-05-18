<?php

declare(strict_types=1);

return [
    'navigation' => 'Avis transporteurs',
    'model' => ['singular' => 'Avis', 'plural' => 'Avis marketplace'],
    'table' => ['column' => [
        'transporter' => 'Transporteur',
        'rating' => 'Note',
        'customer' => 'Client',
        'comment' => 'Commentaire',
        'status' => 'Statut',
        'flagged_at' => 'Signalé',
    ]],
    'filter' => ['rating' => 'Note', 'transporter' => 'Transporteur'],
    'action' => ['publish' => 'Publier', 'hide' => 'Masquer', 'reject' => 'Rejeter (supprimer)'],
    'form' => ['moderation_notes' => 'Notes de modération'],
    'notify' => [
        'moderated' => 'Avis mis à jour (statut : :status).',
        'rejected' => 'Avis rejeté et supprimé.',
    ],
    'view' => ['section_review' => 'Avis', 'section_moderation' => 'Modération'],
];
