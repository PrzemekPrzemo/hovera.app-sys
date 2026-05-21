<?php

declare(strict_types=1);

return [
    'access' => [
        'not_owner' => 'Nie jesteś właścicielem tego konia.',
        'upload_requires_active_boarding' => 'Dodawanie zdjęć wymaga aktywnego pensjonatu w stajni. Po zakończeniu boardingu galeria pozostaje dostępna tylko do odczytu.',
        'cannot_delete_stable' => 'Nie możesz usunąć zdjęcia dodanego przez stajnię.',
        'cannot_delete_other' => 'Możesz usuwać tylko swoje zdjęcia.',
        'path_mismatch' => 'Ścieżka pliku nie należy do tej stajni.',
    ],

    'error' => [
        'too_large' => 'Plik ":name" przekracza limit :max_mb MB.',
        'unsupported_mime' => 'Niewspierany typ pliku ":mime" (":name"). Dozwolone: JPG, PNG, WebP.',
    ],

    'page' => [
        'title' => 'Galeria zdjęć konia',
        'breadcrumb' => 'Galeria',
        'stable' => 'Stajnia',
        'empty_heading' => 'Brak zdjęć',
        'empty_description' => 'Dodaj pierwsze zdjęcie swojego konia — pojawi się tutaj wraz z zdjęciami od stajni.',
    ],

    'form' => [
        'section' => 'Dodaj zdjęcie',
        'file' => 'Plik (JPG/PNG/WebP, max 10 MB)',
        'caption' => 'Opis (opcjonalnie)',
        'upload_button' => 'Wyślij',
        'uploaded' => 'Zdjęcie dodane.',
        'upload_failed' => 'Nie udało się dodać zdjęcia.',
        'no_file' => 'Wybierz plik do wysłania.',
        'delete' => 'Usuń',
        'delete_confirm' => 'Czy na pewno chcesz usunąć to zdjęcie?',
        'deleted' => 'Zdjęcie usunięte.',
    ],

    'uploader' => [
        'you' => 'Ty',
        'stable' => 'Stajnia',
    ],
];
