<?php

declare(strict_types=1);

return [
    'access' => [
        'not_owner' => 'Nie jesteś właścicielem tego konia.',
        'upload_requires_active_boarding' => 'Dodawanie dokumentów wymaga aktywnego pensjonatu w stajni. Po zakończeniu boardingu lista pozostaje dostępna tylko do odczytu.',
        'cannot_delete_stable' => 'Nie możesz usunąć dokumentu dodanego przez stajnię.',
        'cannot_delete_other' => 'Możesz usuwać tylko swoje dokumenty.',
        'path_mismatch' => 'Ścieżka pliku nie należy do tej stajni.',
    ],

    'error' => [
        'too_large' => 'Plik ":name" przekracza limit :max_mb MB.',
        'unsupported_mime' => 'Niewspierany typ pliku ":mime" (":name"). Dozwolone: PDF, Word, Excel, JPG/PNG/WebP.',
        'invalid_kind' => 'Niepoprawny rodzaj dokumentu: :kind.',
    ],

    'page' => [
        'title' => 'Dokumenty konia',
        'breadcrumb' => 'Dokumenty',
        'stable' => 'Stajnia',
        'empty_heading' => 'Brak dokumentów',
        'empty_description' => 'Dodaj pierwszy dokument konia (paszport, kontrakt, ubezpieczenie, świadectwo szczepień).',
        'expired_badge' => 'Wygasł',
        'expiring_soon_badge' => 'Wygasa wkrótce',
    ],

    'form' => [
        'section' => 'Dodaj dokument',
        'file' => 'Plik (PDF/Word/Excel/JPG/PNG, max 25 MB)',
        'name' => 'Nazwa dokumentu',
        'kind' => 'Rodzaj',
        'description' => 'Opis (opcjonalnie)',
        'valid_from' => 'Ważny od',
        'valid_until' => 'Ważny do',
        'upload_button' => 'Wyślij',
        'uploaded' => 'Dokument dodany.',
        'upload_failed' => 'Nie udało się dodać dokumentu.',
        'no_file' => 'Wybierz plik do wysłania.',
        'delete' => 'Usuń',
        'delete_confirm' => 'Czy na pewno chcesz usunąć ten dokument?',
        'deleted' => 'Dokument usunięty.',
        'download' => 'Pobierz',
    ],

    'uploader' => [
        'you' => 'Ty',
        'stable' => 'Stajnia',
    ],

    'table' => [
        'name' => 'Nazwa',
        'kind' => 'Rodzaj',
        'valid_until' => 'Ważny do',
        'uploaded_by' => 'Dodał',
        'added' => 'Data dodania',
        'actions' => 'Akcje',
    ],
];
