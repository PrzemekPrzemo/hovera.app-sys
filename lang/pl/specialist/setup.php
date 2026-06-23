<?php

declare(strict_types=1);

return [
    'page' => [
        'title' => 'Aktywacja konta Hovera',
    ],
    'heading' => 'Ustaw hasło',
    'intro' => 'Witaj. Ustaw hasło, aby aktywować konto specjalisty (:email) w Hoverze.',
    'field' => [
        'password' => 'Nowe hasło (min. 10 znaków, litery + cyfry)',
        'password_confirmation' => 'Potwierdź hasło',
    ],
    'button' => [
        'submit' => 'Aktywuj konto',
    ],
    'error' => [
        'invalid_or_expired' => 'Link aktywacyjny jest niepoprawny lub wygasł. Poproś stajnię o ponowne wysłanie zaproszenia.',
    ],
    'success' => [
        'account_ready' => 'Konto aktywowane. Możesz się zalogować w panelu specjalisty.',
    ],
    'invalid' => [
        'title' => 'Link wygasł',
        'heading' => 'Link aktywacyjny jest niepoprawny lub wygasł',
        'body' => 'Magic linki Hovery wygasają po 7 dniach lub po jednorazowym użyciu. Poproś stajnię, która Cię zaprosiła, o wysłanie nowego zaproszenia.',
    ],
    'completed' => [
        'title' => 'Konto aktywowane',
        'heading' => 'Konto aktywowane ✓',
        'body' => 'Hasło zostało ustawione — możesz już zalogować się do panelu specjalisty. Twoje konto czeka jeszcze na weryfikację przez zespół Hovery (zwykle do 24 godzin roboczych); do tego czasu stajnie widzą przy Tobie oznaczenie „niezweryfikowany".',
        'login_cta' => 'Przejdź do logowania',
    ],
];
