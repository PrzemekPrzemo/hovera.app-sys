<?php

declare(strict_types=1);

return [
    'navigation' => 'Import danych',
    'title' => 'Import danych z Excel/CSV',
    'intro' => 'Zaimportuj listę klientów lub koni z arkusza Excela / pliku CSV. Wspierane źródła: eksport z Nasza Stajnia, Horstable, lub dowolny plik z nagłówkami w pierwszym wierszu.',

    'template' => [
        'clients' => 'Pobierz szablon — klienci',
        'horses' => 'Pobierz szablon — konie',
    ],

    'steps' => [
        'entity' => [
            'title' => 'Co importujesz?',
            'description' => 'Wybierz typ danych do zaimportowania.',
        ],
        'file' => [
            'title' => 'Wgraj plik',
            'description' => 'Akceptujemy .xlsx, .xls, .csv (max 10 MB).',
        ],
        'mapping' => [
            'title' => 'Mapowanie kolumn',
            'description' => 'Dopasuj kolumny z pliku do pól w hovera.',
        ],
        'preview' => [
            'title' => 'Podgląd i import',
            'description' => 'Zweryfikuj pierwsze 5 wierszy przed uruchomieniem importu.',
        ],
    ],

    'fields' => [
        'entity' => 'Typ danych',
        'file' => 'Plik z danymi',
        'clients' => [
            'first_name' => 'Imię',
            'last_name' => 'Nazwisko',
            'email' => 'E-mail',
            'phone' => 'Telefon',
            'street' => 'Ulica',
            'postal_code' => 'Kod pocztowy',
            'city' => 'Miasto',
            'tax_id' => 'NIP',
            'notes' => 'Notatki',
        ],
        'horses' => [
            'name' => 'Imię konia',
            'breed' => 'Rasa',
            'sex' => 'Płeć',
            'color' => 'Maść',
            'birth_date' => 'Data urodzenia',
            'microchip' => 'Microchip',
            'passport_number' => 'Numer paszportu',
            'client_email' => 'E-mail właściciela',
            'notes' => 'Notatki',
        ],
    ],

    'entity' => [
        'clients' => 'Klienci',
        'clients_hint' => 'Właściciele koni / pensjonariusze.',
        'horses' => 'Konie',
        'horses_hint' => 'Konie pensjonatowe i szkółkowe.',
    ],

    'skip' => 'pomiń',
    'upload_first' => 'Wgraj plik w poprzednim kroku, aby móc zmapować kolumny.',
    'parse_pending' => 'Czekam na plik...',
    'parse_summary' => 'Wykryto :rows wierszy danych w :cols kolumnach.',
    'parse_failed' => 'Nie udało się odczytać pliku',
    'no_file' => 'Brak pliku — wróć do kroku 2.',

    'preview' => [
        'empty' => 'Brak danych do wyświetlenia.',
        'status' => 'Status',
        'ok' => 'OK',
        'note' => 'Powyżej widzisz pierwsze 5 wierszy. Pozostałe zostaną zwalidowane podczas importu — wiersze z błędami zostaną pominięte i wylistowane w podsumowaniu.',
    ],

    'actions' => [
        'import' => 'Importuj',
    ],

    'flash' => [
        'success' => 'Zaimportowano :count rekordów.',
        'failed' => 'Pominięto :count wierszy z błędami.',
    ],

    'result' => [
        'heading' => 'Wynik importu',
        'summary' => 'Zaimportowano: :ok · Pominięto: :failed.',
    ],
];
