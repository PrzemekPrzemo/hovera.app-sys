<?php

declare(strict_types=1);

return [
    'title' => ':horse — :tenant',
    'back' => '← Wróć do panelu',

    'info' => [
        'breed' => 'Rasa',
        'sex' => 'Płeć',
        'color' => 'Maść',
        'age' => 'Wiek',
        'age_value' => ':years lat (:year)',
        'microchip' => 'Mikroczip',
        'passport' => 'Paszport',
    ],

    'sections' => [
        'boarding' => 'Pensja i koszty',
        'activities' => 'Co robimy z Twoim koniem',
        'messages' => 'Wiadomości ze stajni',
        'documents' => 'Dokumenty',
        'health' => 'Historia weterynaryjna',
    ],

    'box' => [
        'pill' => '🏠 Box :label',
        'monthly_suffix' => '/mies.',
        'monthly_label' => 'pensjonat: :rate',
    ],

    'services' => [
        'heading' => 'Naliczane usługi',
        'col_item' => 'Pozycja',
        'col_price' => 'Cena',
        'col_frequency' => 'Częstotliwość',
        'col_monthly' => '~mies.',
        'price_per_unit' => ':amount zł / :unit',
    ],

    'cost' => [
        'monthly_label' => 'Szacunkowy koszt miesięczny:',
        'monthly_disclaimer' => 'Bez usług "za użycie" i jednorazowych — te pojawiają się tylko gdy są naliczane.',
    ],

    'messages' => [
        'sent_flash' => '✓ Wiadomość wysłana — stajnia dostała powiadomienie e-mail.',
        'subject_placeholder' => 'Temat (opcjonalnie)',
        'body_placeholder' => 'Napisz coś do stajni…',
        'send' => 'Wyślij',
        'you' => 'Ty',
        'empty' => 'Brak wiadomości — napisz pierwszą.',
        'attachment_fallback' => 'załącznik',
    ],

    'documents' => [
        'uploaded_flash' => '✓ Dokument wgrany.',
        'deleted_flash' => '✓ Dokument usunięty.',
        'name_placeholder' => 'Nazwa dokumentu',
        'description_placeholder' => 'Opis (opcjonalnie)',
        'upload' => 'Wgraj dokument',
        'uploaded_by_stable' => 'Stajnia',
        'uploaded_by_you' => 'Ty',
        'valid_until' => 'ważny do:',
        'download' => '📥 Pobierz',
        'delete' => 'Usuń',
        'delete_confirm' => 'Usunąć dokument?',
        'empty' => 'Brak dokumentów. Wgraj pierwszy.',
    ],

    'health' => [
        'performed_by_label' => 'Wykonał: :name',
        'next_due_label' => 'Następny zabieg: :date',
        'overdue_pill' => 'Przeterminowane',
        'soon_pill' => 'Wkrótce',
        'empty' => 'Brak wpisów weterynaryjnych.',
    ],
];
