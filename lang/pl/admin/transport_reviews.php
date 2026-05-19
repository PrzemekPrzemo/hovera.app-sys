<?php

declare(strict_types=1);

return [
    'navigation' => 'Opinie transporterów',
    'model' => [
        'singular' => 'Opinia',
        'plural' => 'Opinie marketplace\'u',
    ],
    'table' => [
        'column' => [
            'transporter' => 'Przewoźnik',
            'rating' => 'Ocena',
            'customer' => 'Klient',
            'comment' => 'Komentarz',
            'status' => 'Status',
            'flagged_at' => 'Zgłoszono',
        ],
    ],
    'filter' => [
        'rating' => 'Ocena',
        'transporter' => 'Przewoźnik',
        'flagged' => 'Zgłoszone przez przewoźnika',
    ],
    'action' => [
        'publish' => 'Opublikuj',
        'hide' => 'Ukryj',
        'reject' => 'Odrzuć (usuń)',
    ],
    'bulk' => [
        'publish' => 'Opublikuj zaznaczone',
        'publish_done' => 'Opublikowano :count opinii',
        'hide' => 'Ukryj zaznaczone',
        'hide_done' => 'Ukryto :count opinii',
    ],
    'form' => [
        'moderation_notes' => 'Notatki moderatora',
    ],
    'notify' => [
        'moderated' => 'Opinia zaktualizowana (status: :status).',
        'rejected' => 'Opinia odrzucona i usunięta.',
    ],
    'view' => [
        'section_review' => 'Opinia',
        'section_moderation' => 'Moderacja',
    ],
];
