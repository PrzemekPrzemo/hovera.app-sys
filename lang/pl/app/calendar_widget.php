<?php

declare(strict_types=1);

return [
    'action' => [
        'create' => [
            'label' => 'Dodaj rezerwację',
            'modal_heading' => 'Nowa rezerwacja',
            'success' => 'Rezerwacja dodana',
            'conflict_title' => 'Konflikt',
        ],
        'edit' => [
            'label' => 'Edytuj rezerwację',
            'modal_heading' => 'Edycja rezerwacji',
            'success' => 'Rezerwacja zaktualizowana',
            'forbidden_title' => 'Brak uprawnień do edycji tej rezerwacji',
            'forbidden_body' => 'Jako pracownik możesz edytować tylko swoje własne wpisy. Poproś instruktora lub managera o zmianę.',
        ],
        'delete' => [
            'label' => 'Usuń rezerwację',
            'success' => 'Rezerwacja usunięta',
            'forbidden_title' => 'Brak uprawnień do usunięcia tej rezerwacji',
            'forbidden_body' => 'Jako pracownik możesz usuwać tylko swoje własne wpisy.',
        ],
    ],

    'form' => [
        'label' => [
            'type' => 'Typ',
            'starts_at' => 'Początek',
            'ends_at' => 'Koniec',
            'horse' => 'Koń',
            'instructor' => 'Instruktor',
            'arena' => 'Ujeżdżalnia',
            'client' => 'Klient',
            'title' => 'Tytuł',
            'status' => 'Status',
            'notes' => 'Notatki',
        ],
    ],
];
