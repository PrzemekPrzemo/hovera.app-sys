<?php

declare(strict_types=1);

return [
    'title' => 'Witaj w Hovera',
    'navigation_label' => 'Pierwsze kroki',
    'welcome' => [
        'heading' => 'Krok 1 z 3 — Twoja stajnia w Hoverze',
        'body' => 'Przeprowadzimy Cię przez 3 najważniejsze ustawienia. Każdy krok ma link do właściwej strony — wypełnij na spokojnie albo pomiń i wróć później.',
    ],
    'steps' => [
        'company' => [
            'title' => 'Dane firmy',
            'description' => 'NIP, nazwa, adres, regulamin',
            'body' => 'Wpisz NIP stajni — kliknij „Pobierz z GUS / VIES" i automatycznie uzupełnimy nazwę i adres. Te dane trafią na faktury wystawiane klientom.',
            'cta_hint' => 'Otwórz w nowej karcie:',
            'cta' => 'Ustawienia stajni',
        ],
        'ksef' => [
            'title' => 'KSeF',
            'description' => 'Certyfikat + środowisko (test / prod)',
            'body' => 'Od 2026-02 KSeF jest obowiązkowy w PL. Wgraj certyfikat (PFX lub PEM) i wybierz środowisko. Możesz zacząć od trybu test, prod włączysz przed lutym.',
            'cta_hint' => 'Otwórz w nowej karcie:',
            'cta' => 'Ustawienia KSeF',
        ],
        'first_record' => [
            'title' => 'Pierwszy klient lub koń',
            'description' => 'Test panelu na realnym przypadku',
            'body' => 'Najszybsza nauka panelu to dodanie pierwszego rekordu. Wpisz pierwszego klienta (GUS lookup tam też zadziała), albo pierwszego konia — wszystko po-edytujesz później.',
            'cta_client' => 'Dodaj pierwszego klienta',
            'cta_horse' => 'Dodaj pierwszego konia',
        ],
    ],
    'action' => [
        'finish' => 'Zakończ wizard',
        'skip' => 'Pomiń wizard',
    ],
    'notify' => [
        'completed_title' => 'Wizard ukończony',
        'completed_body' => 'Powodzenia! Gdy będziesz potrzebować pomocy — `?` w panelu pokazuje skróty, a `Pomoc` w menu otwiera dokumentację.',
        'skipped_title' => 'Wizard pominięty',
        'skipped_body' => 'Zawsze możesz wrócić do skonfigurowania KSeF i danych firmy w sekcji Ustawienia.',
    ],
];
