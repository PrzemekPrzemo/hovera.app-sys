<?php

declare(strict_types=1);

return [
    'access' => [
        'not_owner' => 'Nie jesteś właścicielem tego konia.',
        'send_requires_active_boarding' => 'Wysyłanie wiadomości wymaga aktywnego pensjonatu (boardingu) w stajni. Boarding zakończony — historyczne wiadomości pozostają do odczytu.',
    ],

    'attachment' => [
        'too_large' => 'Załącznik ":name" przekracza limit :max_mb MB.',
        'unsupported_mime' => 'Niewspierany typ pliku ":mime" (":name"). Dozwolone: zdjęcia, PDF, wideo MP4/MOV.',
    ],

    'page' => [
        'title' => 'Wiadomości ze stajnią',
        'breadcrumb' => 'Wiadomości',
        'thread_with' => 'Wątek z',
        'empty_heading' => 'Brak wiadomości',
        'empty_description' => 'Napisz pierwszą wiadomość do stajni — pojawi się tutaj wraz z odpowiedziami.',
    ],

    'form' => [
        'section' => 'Nowa wiadomość',
        'subject' => 'Temat (opcjonalnie)',
        'body' => 'Treść',
        'attachments' => 'Załączniki',
        'attachments_hint' => 'Max 10 plików, do 25 MB każdy. Zdjęcia (JPG/PNG/WebP), PDF, wideo MP4/MOV.',
        'send' => 'Wyślij',
        'sent_title' => 'Wiadomość wysłana.',
        'empty_body' => 'Treść wiadomości nie może być pusta.',
        'attachments_failed' => 'Nie udało się załadować załączników.',
    ],
];
