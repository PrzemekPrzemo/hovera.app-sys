<?php

declare(strict_types=1);

return [
    'title' => 'Zarezerwuj lekcję',
    'back' => '← Wróć do panelu',
    'heading' => 'Zarezerwuj lekcję',
    'subtitle' => 'Sam wybierz konia, instruktora i termin · :tenant',
    'errors_heading' => 'Sprawdź formularz:',

    'no_horses' => 'Nie masz przypisanych koni do tego konta. Skontaktuj się ze stajnią.',
    'no_dates' => 'Brak wolnych terminów u tego instruktora w najbliższym czasie.',
    'no_slots' => 'Brak wolnych godzin tego dnia. Wybierz inny dzień.',

    'label' => [
        'horse' => 'Twój koń',
        'horse_for' => 'Koń, na którym pojedziesz',
        'instructor' => 'Instruktor',
        'instructor_placeholder' => '— wybierz instruktora —',
        'day' => 'Dzień',
        'slot' => 'Godzina',
        'notes' => 'Uwagi (opcjonalnie)',
        'notes_placeholder' => 'np. preferowany manaż / poziom zaawansowania',
    ],

    'actions' => [
        'submit' => 'Wyślij prośbę o rezerwację',
    ],

    'errors' => [
        'disabled' => 'Online booking jest wyłączony dla tej stajni.',
        'horse_invalid' => 'Wybrany koń nie należy do Twojego konta.',
        'instructor_invalid' => 'Instruktor jest niedostępny.',
        'slot_taken' => 'Niestety ten termin został właśnie zajęty. Wybierz inny.',
    ],

    'success_flash' => '✓ Wysłaliśmy prośbę o rezerwację. Stajnia potwierdzi i odezwiemy się mailem.',
    'disabled_flash' => 'Online booking jest wyłączony dla tej stajni — skontaktuj się ze stajnią telefonicznie.',
];
