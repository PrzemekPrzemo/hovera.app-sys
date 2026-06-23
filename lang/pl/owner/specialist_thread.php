<?php

declare(strict_types=1);

return [
    'nav' => 'Specjaliści — wiadomości',
    'model' => 'Wątek ze specjalistą',
    'model_plural' => 'Wątki ze specjalistami',
    'messages' => 'Wiadomości',

    'form' => [
        'specialist' => 'Specjalista',
        'specialist_hint' => 'Lista zawiera specjalistów, których zaprosiłeś. Aby dodać nowego, użyj „Zaproś specjalistę".',
        'horse' => 'Koń (opcjonalnie)',
        'horse_placeholder' => '— wątek ogólny —',
        'horse_hint' => 'Wskazany koń zostaje udostępniony specjaliście w tym wątku.',
        'subject' => 'Temat',
        'body' => 'Treść wiadomości',
    ],

    'table' => [
        'subject' => 'Temat',
        'specialist' => 'Specjalista',
        'last_message' => 'Ostatnia wiadomość',
    ],

    'action' => [
        'new' => 'Nowy wątek',
        'open' => 'Otwórz',
        'reply' => 'Odpowiedz',
    ],

    'sender' => [
        'specialist' => 'Specjalista',
        'you' => 'Ty',
    ],

    'invite' => [
        'label' => 'Zaproś specjalistę',
        'email' => 'E-mail specjalisty',
        'display_name' => 'Imię i nazwisko',
        'specialty' => 'Specjalność',
        'modal_heading' => 'Zaproś specjalistę do Hovery',
        'modal_description' => 'Wyślemy 7-dniowy link aktywacyjny. Po ustawieniu hasła specjalista będzie mógł odpisywać na Twoje wiadomości.',
        'submit' => 'Wyślij zaproszenie',
        'no_context' => 'Brak kontekstu konta — odśwież stronę i spróbuj ponownie.',
        'sent_title' => 'Zaproszenie wysłane',
        'sent_body' => 'Specjalista (:email) otrzyma e-mail z linkiem aktywacyjnym.',
        'exists_title' => 'Specjalista ma już konto',
        'exists_body' => ':email jest już zarejestrowany — możesz od razu założyć wątek.',
    ],

    'error' => [
        'no_context' => 'Brak kontekstu konta — odśwież stronę i spróbuj ponownie.',
    ],
];
