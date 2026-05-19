<?php

declare(strict_types=1);

return [
    'navigation' => 'Transporteur-Bewertungen',
    'model' => ['singular' => 'Bewertung', 'plural' => 'Marketplace-Bewertungen'],
    'table' => ['column' => [
        'transporter' => 'Transporteur',
        'rating' => 'Bewertung',
        'customer' => 'Kunde',
        'comment' => 'Kommentar',
        'status' => 'Status',
        'flagged_at' => 'Gemeldet',
    ]],
    'filter' => [
        'rating' => 'Bewertung',
        'transporter' => 'Transporteur',
        'flagged' => 'Vom Spediteur gemeldet',
    ],
    'action' => ['publish' => 'Veröffentlichen', 'hide' => 'Ausblenden', 'reject' => 'Ablehnen (löschen)'],
    'bulk' => [
        'publish' => 'Markierte veröffentlichen',
        'publish_done' => ':count Bewertungen veröffentlicht',
        'hide' => 'Markierte ausblenden',
        'hide_done' => ':count Bewertungen ausgeblendet',
    ],
    'form' => ['moderation_notes' => 'Moderationsnotizen'],
    'notify' => [
        'moderated' => 'Bewertung aktualisiert (Status: :status).',
        'rejected' => 'Bewertung abgelehnt und gelöscht.',
    ],
    'view' => ['section_review' => 'Bewertung', 'section_moderation' => 'Moderation'],
];
