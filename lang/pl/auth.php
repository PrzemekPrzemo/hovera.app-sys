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

    'tenant_select' => [
        'title' => 'Wybierz konto — Hovera',
        'heading' => 'Wybierz konto',
        'intro' => 'Twoje konto ma dostęp do :count tenantów (stajni / firm transportowych). Wybierz, do którego chcesz się zalogować.',
        'role_label' => ':slug · rola: :role',
        'submit' => 'Przejdź do panelu',
        'no_access' => 'Brak dostępu do wybranego konta.',
        'type_stable' => 'Stajnia',
        'type_transporter' => 'Firma transportowa',
        'status_provisioning' => 'Oczekuje na weryfikację',
    ],

    'no_tenants' => [
        'title' => 'Brak dostępnych kont — Hovera',
        'heading' => 'Brak dostępnych kont',
        'intro' => 'Twoje konto nie jest jeszcze przypisane do żadnej stajni ani firmy transportowej, lub Twój dostęp został cofnięty. Skontaktuj się z administratorem aby uzyskać dostęp.',
        'logout' => 'Wyloguj się',
    ],

    'invitation_accept' => [
        'title' => 'Aktywuj konto — Hovera',
        'heading' => 'Ustaw hasło',
        'intro_with_tenant' => 'Dołączasz do stajni <strong>:tenant</strong>.',
        'intro_account' => 'Konto: <strong>:email</strong>.',
        'intro_pwd' => 'Wybierz hasło (min. 12 znaków), aby aktywować konto.',
        'password' => 'Nowe hasło',
        'password_confirmation' => 'Powtórz hasło',
        'submit' => 'Aktywuj konto i zaloguj',
    ],
];
