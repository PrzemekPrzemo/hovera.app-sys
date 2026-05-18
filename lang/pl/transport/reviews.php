<?php

declare(strict_types=1);

return [
    'navigation' => 'Opinie klientów',
    'model' => [
        'singular' => 'Opinia',
        'plural' => 'Opinie',
    ],
    'table' => [
        'column' => [
            'rating' => 'Ocena',
            'customer' => 'Klient',
            'comment' => 'Komentarz',
            'status' => 'Status',
            'responded' => 'Odp.',
            'submitted_at' => 'Wystawiono',
        ],
    ],
    'filter' => [
        'rating' => 'Ocena',
    ],
    'status' => [
        'invited' => 'zaproszony',
        'published' => 'opublikowane',
        'hidden' => 'ukryte',
        'flagged' => 'zgłoszone',
        'expired' => 'wygasłe',
    ],
    'action' => [
        'respond' => 'Odpowiedz publicznie',
        'flag' => 'Zgłoś do moderacji',
    ],
    'form' => [
        'response_label' => 'Twoja odpowiedź',
        'response_helper' => 'Odpowiedź będzie widoczna publicznie pod opinią. Możesz ją później edytować.',
        'flag_reason_label' => 'Powód zgłoszenia',
        'flag_reason_helper' => 'Opisz dlaczego opinia narusza zasady (zniesławienie, fake, niezgodność z faktami). Zespół Hovera zweryfikuje.',
    ],
    'notify' => [
        'response_saved' => 'Odpowiedź zapisana.',
        'flagged_title' => 'Opinia zgłoszona do moderacji',
        'flagged_body' => 'Opinia jest tymczasowo ukryta. Zespół Hovera podejmie decyzję.',
    ],
    'stats' => [
        'average' => 'Średnia ocena',
        'count' => 'Liczba opinii',
        'count_desc' => 'opublikowane łącznie',
        'five_stars' => 'Ocen 5★',
        'no_reviews_yet' => 'Brak opinii',
    ],
    'view' => [
        'section_review' => 'Opinia',
        'section_response' => 'Twoja odpowiedź',
        'section_moderation' => 'Moderacja',
    ],
];
