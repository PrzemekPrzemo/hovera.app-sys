<?php

declare(strict_types=1);

return [
    'navigation' => 'Avis clients',
    'model' => ['singular' => 'Avis', 'plural' => 'Avis'],
    'table' => ['column' => [
        'rating' => 'Note',
        'customer' => 'Client',
        'comment' => 'Commentaire',
        'status' => 'Statut',
        'responded' => 'Réponse',
        'submitted_at' => 'Soumis',
    ]],
    'filter' => ['rating' => 'Note'],
    'status' => [
        'invited' => 'invité',
        'published' => 'publié',
        'hidden' => 'masqué',
        'flagged' => 'signalé',
        'expired' => 'expiré',
    ],
    'action' => ['respond' => 'Répondre publiquement', 'flag' => 'Signaler pour modération'],
    'form' => [
        'response_label' => 'Votre réponse',
        'response_helper' => 'Votre réponse est affichée publiquement sous l\'avis. Vous pouvez la modifier plus tard.',
        'flag_reason_label' => 'Raison du signalement',
        'flag_reason_helper' => 'Expliquez pourquoi cet avis enfreint les règles. L\'équipe Hovera examinera.',
    ],
    'notify' => [
        'response_saved' => 'Réponse enregistrée.',
        'flagged_title' => 'Avis signalé pour modération',
        'flagged_body' => 'L\'avis est temporairement masqué. L\'équipe Hovera décidera.',
    ],
    'stats' => [
        'average' => 'Note moyenne',
        'count' => 'Total des avis',
        'count_desc' => 'publiés',
        'five_stars' => 'Avis 5★',
        'no_reviews_yet' => 'Aucun avis pour le moment',
    ],
    'view' => ['section_review' => 'Avis', 'section_response' => 'Votre réponse', 'section_moderation' => 'Modération'],
];
