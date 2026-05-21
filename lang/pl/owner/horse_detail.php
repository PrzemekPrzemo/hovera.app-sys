<?php

declare(strict_types=1);

return [
    'title' => [
        'fallback' => 'Szczegóły konia',
    ],

    'breadcrumb' => 'Szczegóły boardingu',

    'hero' => [
        'boarding_at' => 'Boarduje w stajni',
        'since' => 'Od',
    ],

    'section' => [
        'identification' => 'Identyfikacja',
        'current_box' => 'Aktualny boks',
        'boarding_services' => 'Aktywne usługi pensjonatu',
        'notes' => 'Notatki stajni',
    ],

    'field' => [
        'name' => 'Imię',
        'breed' => 'Rasa',
        'sex' => 'Płeć',
        'color' => 'Maść',
        'birth_date' => 'Data urodzenia',
        'age' => ':years lat',
        'passport_number' => 'Nr paszportu',
        'microchip' => 'Mikrochip',
        'ueln' => 'UELN',
        'monthly_rate' => 'Stawka miesięczna',
        'assigned_at' => 'Wprowadzony do boksu: :date',
        'estimated_monthly_cost' => 'Szacunkowy koszt miesięczny',
        'estimated_monthly_cost_hint' => 'Pensjonat boksu + suma aktywnych usług. Rzeczywista faktura może zawierać dodatkowe pozycje (one-shot, leczenia, etc.).',
    ],

    'frequency' => [
        'daily' => 'codziennie',
        'weekly' => 'tygodniowo',
        'monthly' => 'miesięcznie',
        'per_use' => 'wg zużycia',
        'once' => 'jednorazowo',
    ],

    'table' => [
        'service_name' => 'Usługa',
        'frequency' => 'Częstotliwość',
        'price' => 'Cena',
    ],

    'empty' => [
        'no_box' => 'Stajnia nie przypisała jeszcze boksu temu koniowi.',
        'no_services' => 'Brak aktywnych usług pensjonatu poza pensjonatem boksu.',
    ],

    'upcoming' => [
        'heading' => 'Wkrótce w panelu właściciela',
        'timeline' => 'Oś czasu wszystkich działań stajni na koniu (wizyty wet, zmiany boksu, ważenia, aktywności)',
        'invoices' => 'Faktury wystawione przez stajnię z breakdown\'em per koń + płatność online',
        'messages' => 'Wiadomości z stajnią — wątek per koń z załącznikami (zdjęcia, dokumenty)',
        'files' => 'Galeria zdjęć i dokumentów konia (paszport, kontrakty, świadectwa szczepień)',
    ],

    'access' => [
        'denied' => 'Brak dostępu do tego konia. Wymagany aktywny pensjonat (boarding) w stajni.',
        'sync_rift' => 'Dane konia nie są jeszcze dostępne w stajni — odśwież stronę za chwilę lub skontaktuj się z opiekunem.',
    ],
];
