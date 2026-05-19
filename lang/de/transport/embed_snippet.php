<?php

declare(strict_types=1);

return [
    'navigation' => 'Embed-Formular',
    'title' => 'Anfrageformular zum Einbetten',

    'section' => [
        'origins' => 'Erlaubte Domains',
        'origins_description' => 'Nur die aufgeführten Domains können das Formular absenden. Vollständige URL mit Schema (`https://` oder `http://`), ohne Schrägstrich am Ende.',
        'token' => 'API-Token',
        'token_description' => 'Geheimnis, das per `X-Hovera-Embed-Token` Header geprüft wird. Bei Neugenerierung wird der alte Token sofort ungültig — aktualisieren Sie das Snippet auf Ihren Seiten.',
        'snippet' => 'Snippet zum Einfügen',
        'snippet_description' => 'Kopieren und in den HTML-Code Ihrer Seite einfügen. JS sendet die Anfrage an Hovera; Transportzahlungen gehen direkt an Sie (Hovera vermittelt keine Zahlungen).',
    ],

    'form' => [
        'origin_url' => 'Seiten-URL (Origin)',
        'add_origin' => 'Domain hinzufügen',
        'token_status_label' => 'Token-Status',
        'token_missing' => 'Kein Token — generieren Sie einen, um das Embed zu aktivieren.',
        'token_present' => 'Token gesetzt (:preview).',
    ],

    'action' => [
        'save' => 'Domains speichern',
        'regenerate_token' => 'Neuen Token generieren',
        'regenerate_token_confirm' => 'Der alte Token wird sofort ungültig — alle bestehenden Embeds müssen aktualisiert werden. Fortfahren?',
        'copy' => 'Snippet kopieren',
        'copied' => 'Kopiert!',
    ],

    'notify' => [
        'saved' => 'Domains gespeichert',
        'saved_body' => 'Aktive Domains: :count.',
        'token_regenerated' => 'Neuer Token generiert',
        'token_regenerated_body' => 'Der alte Token ist nicht mehr gültig. Aktualisieren Sie das Snippet auf Ihren Seiten.',
    ],

    'snippet' => [
        'requires_token' => '<!-- Generieren Sie zuerst oben einen API-Token, um den Snippet-Code zu sehen. -->',
    ],
];
