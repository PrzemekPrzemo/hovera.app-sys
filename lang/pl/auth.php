<?php

declare(strict_types=1);

return [
    // Default Laravel keys
    'failed' => 'Nieprawidłowe dane logowania.',
    'password' => 'Nieprawidłowe hasło.',
    'throttle' => 'Zbyt wiele prób logowania. Spróbuj ponownie za :seconds sekund.',

    // Hovera-specific
    'login' => [
        'title' => 'Zaloguj się — hovera',
        'heading' => 'Zaloguj się',
        'email' => 'Email',
        'password' => 'Hasło',
        'remember' => 'Zapamiętaj mnie',
        'submit' => 'Zaloguj',
        'forgot_password' => 'Zapomniałeś hasła?',
        'no_account' => 'Nie masz konta?',
        'register' => 'Zarejestruj się',
    ],

    'logout' => 'Wyloguj się',

    'two_factor' => [
        'setup_title' => 'Konfiguracja 2FA — hovera',
        'setup_heading' => 'Włącz uwierzytelnianie dwuskładnikowe (2FA)',
        'setup_intro' => 'Zeskanuj QR aplikacją uwierzytelniającą (Google Authenticator, Authy, 1Password) i wpisz wygenerowany sześciocyfrowy kod, aby potwierdzić.',
        'manual_entry' => 'Albo wpisz sekret ręcznie:',
        'code_label' => 'Kod 2FA',
        'confirm' => 'Potwierdź i włącz',
        'challenge_title' => 'Weryfikacja 2FA — hovera',
        'challenge_heading' => 'Wpisz kod 2FA',
        'challenge_intro' => 'Wpisz sześciocyfrowy kod z aplikacji uwierzytelniającej, lub kod jednorazowy z listy kodów odzyskiwania.',
        'remember_device' => 'Zapamiętaj to urządzenie na 14 dni',
        'submit_challenge' => 'Zaloguj',
        'invalid_code' => 'Nieprawidłowy kod.',
        'recovery_codes_title' => 'Kody odzyskiwania — hovera',
        'recovery_codes_heading' => 'Twoje kody odzyskiwania',
        'recovery_codes_intro' => 'Zapisz te kody w bezpiecznym miejscu. Każdy działa tylko raz — możesz ich użyć, jeśli stracisz dostęp do aplikacji uwierzytelniającej.',
        'recovery_codes_continue' => 'Zapisałem kody, kontynuuj',
    ],

    'password_reset' => [
        'request_title' => 'Reset hasła',
        'email_sent' => 'Wysłaliśmy link do resetu hasła na Twój email.',
        'reset_title' => 'Ustaw nowe hasło',
        'reset_button' => 'Zresetuj hasło',
    ],
];
