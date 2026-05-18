<?php

declare(strict_types=1);

return [
    'navigation' => 'Lieblings-Spediteure',
    'title' => 'Lieblings-Spediteure',

    'intro' => [
        'title' => 'Lieblings-Spediteure',
        'body' => 'Markieren Sie bis zu :limit Transportunternehmen (aktuell :current). Bei der Anfrage füllen wir die Direct-Liste vor — Sie wählen 1-3.',
    ],

    'search_placeholder' => 'Suche nach Name, USt-ID, Slug…',
    'empty' => 'Keine verifizierten Transportunternehmen.',

    'action' => [
        'add' => 'Zu Favoriten',
        'remove' => 'Entfernen',
    ],

    'notify' => [
        'added' => 'Zu Favoriten hinzugefügt',
        'removed' => 'Aus Favoriten entfernt',
        'limit_reached' => 'Favoritenlimit erreicht',
        'limit_body' => 'Maximal :limit Favoriten. Bitte erst einen entfernen.',
        'error' => 'Fehler',
    ],
];
