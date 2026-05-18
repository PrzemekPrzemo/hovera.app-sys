<?php

declare(strict_types=1);

return [
    'navigation' => 'Kundenbewertungen',
    'model' => ['singular' => 'Bewertung', 'plural' => 'Bewertungen'],
    'table' => ['column' => [
        'rating' => 'Bewertung',
        'customer' => 'Kunde',
        'comment' => 'Kommentar',
        'status' => 'Status',
        'responded' => 'Antwort',
        'submitted_at' => 'Eingereicht',
    ]],
    'filter' => ['rating' => 'Bewertung'],
    'status' => [
        'invited' => 'eingeladen',
        'published' => 'veröffentlicht',
        'hidden' => 'ausgeblendet',
        'flagged' => 'gemeldet',
        'expired' => 'abgelaufen',
    ],
    'action' => ['respond' => 'Öffentlich antworten', 'flag' => 'Zur Moderation melden'],
    'form' => [
        'response_label' => 'Ihre Antwort',
        'response_helper' => 'Ihre Antwort wird öffentlich unter der Bewertung angezeigt. Sie können sie später bearbeiten.',
        'flag_reason_label' => 'Grund für die Meldung',
        'flag_reason_helper' => 'Beschreiben Sie, warum diese Bewertung gegen die Regeln verstößt. Das Hovera-Team prüft sie.',
    ],
    'notify' => [
        'response_saved' => 'Antwort gespeichert.',
        'flagged_title' => 'Bewertung zur Moderation gemeldet',
        'flagged_body' => 'Die Bewertung ist vorübergehend ausgeblendet. Das Hovera-Team entscheidet.',
    ],
    'stats' => [
        'average' => 'Durchschnittsbewertung',
        'count' => 'Bewertungen insgesamt',
        'count_desc' => 'veröffentlicht',
        'five_stars' => '5-Sterne-Bewertungen',
        'no_reviews_yet' => 'Noch keine Bewertungen',
    ],
    'view' => [
        'section_review' => 'Bewertung',
        'section_response' => 'Ihre Antwort',
        'section_moderation' => 'Moderation',
    ],
];
