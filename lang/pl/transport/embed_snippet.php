<?php

declare(strict_types=1);

return [
    'navigation' => 'Embed formularz',
    'title' => 'Formularz zapytania do osadzenia',

    'section' => [
        'origins' => 'Dozwolone domeny',
        'origins_description' => 'Tylko podane domeny mogą wysyłać formularz. Wpisz pełny adres ze schematem (`https://` lub `http://`), bez końcowego ukośnika.',
        'token' => 'Token API',
        'token_description' => 'Sekret weryfikowany w nagłówku `X-Hovera-Embed-Token`. Po wygenerowaniu nowego tokenu stary natychmiast przestaje działać — zaktualizuj snippet na swoich stronach.',
        'snippet' => 'Snippet do wklejenia',
        'snippet_description' => 'Skopiuj i wklej w HTML swojej strony. JS posta zapytania do Hovery; pieniądze za transport idą wprost do Ciebie (pośrednik nie pośredniczy w płatnościach).',
    ],

    'form' => [
        'origin_url' => 'Adres strony (Origin)',
        'add_origin' => 'Dodaj domenę',
        'token_status_label' => 'Status tokenu',
        'token_missing' => 'Brak tokenu — wygeneruj go aby aktywować embed.',
        'token_present' => 'Token ustawiony (:preview).',
    ],

    'action' => [
        'save' => 'Zapisz domeny',
        'regenerate_token' => 'Wygeneruj nowy token',
        'regenerate_token_confirm' => 'Stary token natychmiast przestanie działać — wszystkie istniejące embed-y trzeba będzie zaktualizować. Kontynuować?',
        'copy' => 'Kopiuj snippet',
        'copied' => 'Skopiowano!',
    ],

    'notify' => [
        'saved' => 'Domeny zapisane',
        'saved_body' => 'Aktywne domeny: :count.',
        'token_regenerated' => 'Wygenerowano nowy token',
        'token_regenerated_body' => 'Stary token nie działa od tej chwili. Zaktualizuj snippet na swoich stronach.',
    ],

    'snippet' => [
        'requires_token' => '<!-- Najpierw wygeneruj token API powyżej, żeby zobaczyć kod snippetu. -->',
    ],
];
