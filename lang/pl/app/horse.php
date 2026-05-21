<?php

declare(strict_types=1);

return [
    'sex' => [
        'mare' => 'Klacz',
        'gelding' => 'Wałach',
        'stallion' => 'Ogier',
        'breeding_stallion' => 'Ogier kryjący',
    ],

    'form' => [
        'section' => [
            'identification' => 'Identyfikacja',
            'characteristics' => 'Charakterystyka',
            'boarding' => 'Pensja — usługi naliczane',
            'boarding_description' => 'Zaznacz które pozycje cennika dotyczą tego konia. Klient zobaczy je w portalu z miesięczną szacunkową kwotą.',
            'notes' => 'Notatki',
            'sport' => 'Sport (LiveJumping)',
            'sport_help' => 'Wklej URL profilu konia z LiveJumping.com — pokażemy palmares i nadchodzące starty.',
        ],
        'label' => [
            'name' => 'Imię',
            'owner' => 'Właściciel',
            'owner_placeholder' => '— stajnia —',
            'box' => 'Box',
            'box_placeholder' => '— bez przypisania —',
            'microchip' => 'Mikrochip',
            'passport_number' => 'Nr paszportu',
            'ueln' => 'UELN',
            'sex' => 'Płeć',
            'breed' => 'Rasa',
            'color' => 'Maść',
            'birth_date' => 'Data urodzenia',
            'boarding_services' => 'Usługi z cennika',
            'livejumping_profile_url' => 'URL profilu LiveJumping',
            'livejumping_palmares' => 'Palmares',
        ],
        'helper' => [
            'box' => 'Zmiana boxa zarejestruje historię w "Boxy → Historia przypisań".',
            'ueln' => 'Universal Equine Life Number',
            'boarding_services' => 'Cennik konfigurujesz w "Stajnia → Cennik pensji". Override ceny per koń (np. zniżka) ustawiasz tam ręcznie po utworzeniu wpisu.',
            'livejumping_profile_url' => 'Skopiuj adres strony profilu z livejumping.com — np. https://livejumping.com/horse/12345/romeo',
            'livejumping_no_profile' => 'Wklej URL profilu LJ powyżej, aby zobaczyć palmares.',
            'livejumping_fetch_failed' => 'Nie udało się pobrać danych z LiveJumping (sprawdź URL lub spróbuj później).',
        ],
        'stats' => [
            'starts' => 'Starty',
            'wins' => 'Zwycięstwa',
            'placings' => 'Miejsca w czołówce',
            'ranking_points' => 'Punkty rankingowe',
            'recent_results' => 'Ostatnie wyniki',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Imię',
            'breed' => 'Rasa',
            'sex' => 'Płeć',
            'color' => 'Maść',
            'birth_date' => 'Ur.',
            'owner' => 'Właściciel',
            'owner_placeholder' => '— stajnia —',
            'created_at' => 'Dodany',
        ],
    ],

    'action' => [
        'import_from_registry' => [
            'label' => 'Importuj z rejestru',
            'modal_heading' => 'Dodaj konia z rejestru właścicieli',
            'modal_description' => 'Wpisz email właściciela — system pokaże listę jego koni w centralnym rejestrze. Po wyborze wysyłamy request boardingu, właściciel zatwierdza w swoim panelu.',
            'owner_email' => 'Email właściciela',
            'owner_email_helper' => 'Email z którego właściciel zarejestrował się w Hovera.',
            'horse' => 'Koń',
            'horse_helper' => 'Lista koni należących do tego właściciela w centralnym rejestrze.',
            'no_passport' => 'brak paszportu',
            'submit' => 'Wyślij request boardingu',
            'no_tenant' => 'Brak aktywnego kontekstu stajni — spróbuj ponownie.',
            'horse_missing' => 'Wybrany koń nie istnieje w rejestrze (usunięty?).',
            'success_title' => 'Request boardingu wysłany',
            'success_body' => 'Konia „:name" oczekuje akceptacji właściciela. Status: :status.',
            'lookup' => [
                'user_not_found' => 'Brak właściciela z tym emailem w systemie. Sprawdź pisownię lub poproś go o rejestrację na /register/horse-owner.',
                'no_horses' => 'Właściciel :email istnieje, ale nie ma jeszcze koni w centralnym rejestrze.',
                'found' => 'Znaleziono :count konia/koni — wybierz z listy poniżej.',
            ],
        ],
    ],
];
