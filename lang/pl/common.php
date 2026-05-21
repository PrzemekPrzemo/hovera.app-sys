<?php

declare(strict_types=1);

return [
    'actions' => [
        'save' => 'Zapisz',
        'cancel' => 'Anuluj',
        'delete' => 'Usuń',
        'edit' => 'Edytuj',
        'create' => 'Utwórz',
        'view' => 'Podgląd',
        'close' => 'Zamknij',
        'confirm' => 'Potwierdź',
        'back' => 'Wstecz',
        'next' => 'Dalej',
        'submit' => 'Zatwierdź',
        'search' => 'Szukaj',
        'filter' => 'Filtruj',
        'reset' => 'Resetuj',
        'export' => 'Eksportuj',
        'import' => 'Importuj',
        'download' => 'Pobierz',
        'upload' => 'Wgraj',
    ],

    'status' => [
        'active' => 'Aktywny',
        'inactive' => 'Nieaktywny',
        'pending' => 'Oczekujący',
        'archived' => 'Zarchiwizowany',
        'deleted' => 'Usunięty',
        'trashed' => 'W koszu',
    ],

    'fields' => [
        'name' => 'Nazwa',
        'email' => 'Email',
        'phone' => 'Telefon',
        'address' => 'Adres',
        'website' => 'Strona WWW',
        'description' => 'Opis',
        'notes' => 'Notatki',
        'created_at' => 'Utworzono',
        'updated_at' => 'Zmodyfikowano',
        'deleted_at' => 'Usunięto',
    ],

    'language' => [
        'switcher' => 'Język',
        'pl' => 'Polski',
        'en' => 'English',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'ru' => 'Русский',
    ],

    'field' => [
        'locale' => 'Preferowany język',
        'locale_help' => 'Wybierz domyślny język interfejsu. Możesz w każdej chwili zmienić.',
    ],

    'yes' => 'Tak',
    'no' => 'Nie',
    'none' => 'Brak',
    'all' => 'Wszystkie',
    'or' => 'lub',
    'dismiss' => 'Ukryj',

    'gus_lookup' => [
        'label' => 'Pobierz z GUS',
        'invalid_nip' => 'NIP jest niepoprawny (10 cyfr + suma kontrolna).',
        'not_found' => 'Nie znaleziono firmy o tym NIP. Sprawdź pisownię lub uzupełnij ręcznie.',
        'success' => 'Dane pobrane',
        'success_body' => 'Źródło: :sources. Sprawdź adres i zaktualizuj jeśli potrzeba.',
    ],
];
