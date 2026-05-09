<?php

declare(strict_types=1);

return [
    'title' => 'Załóż stajnię',
    'thanks_title' => 'Sprawdź mail',

    'heading' => 'Załóż stajnię w hovera',
    'subtitle' => 'Wypełnij 4 pola, dostaniesz mail z linkiem do ustawienia hasła. Bez karty kredytowej.',

    'trial_strong' => '🎉 30 dni za darmo',
    'trial_text' => 'Pełna funkcjonalność. Bez karty. Bez automatycznej zamiany na płatny — wybierzesz plan dopiero gdy będziesz pewny że chcesz zostać.',

    'label' => [
        'name' => 'Nazwa stajni',
        'slug' => 'Adres URL stajni',
        'owner_name' => 'Twoje imię i nazwisko',
        'owner_email' => 'E-mail',
        'terms' => 'Akceptuję <a href="/regulamin" target="_blank">regulamin</a> i <a href="/polityka-prywatnosci" target="_blank">politykę prywatności</a>',
    ],

    'placeholder' => [
        'name' => 'Stajnia Pegaz',
        'owner_name' => 'Anna Kowalska',
    ],

    'helper' => [
        'name' => 'Tak będzie wyświetlane w panelu i na publicznej stronie.',
        'slug' => 'Same małe litery, cyfry i myślniki. Min. 3 znaki.',
        'owner_email' => 'Tu wyślemy link do ustawienia hasła.',
    ],

    'action' => [
        'submit' => 'Załóż konto + 30 dni za darmo',
    ],

    'footer' => [
        'demo' => 'Najpierw zobacz demo',
        'pricing' => 'Zobacz cennik',
        'login' => 'Mam już konto',
    ],

    'errors' => [
        'heading' => 'Sprawdź formularz:',
        'slug_format' => 'Adres może zawierać tylko małe litery, cyfry i myślniki (myślnik nie na początku/końcu).',
        'slug_taken' => 'Ten adres jest już zajęty — spróbuj innego, np. dorzuć miasto albo skrót.',
        'terms' => 'Musisz zaakceptować regulamin.',
        'provisioning_failed' => 'Coś poszło nie tak po naszej stronie. Spróbuj ponownie za chwilę albo napisz na support@hovera.app.',
    ],

    'thanks_heading' => '✓ Konto założone',
    'thanks_subtitle' => 'Stajnia :tenant została utworzona. Wysłaliśmy mail z linkiem do ustawienia hasła.',
    'thanks_step_1' => 'Sprawdź skrzynkę odbiorczą (link ważny 7 dni).',
    'thanks_step_2' => 'Kliknij „Akceptuj zaproszenie" w mailu.',
    'thanks_step_3' => 'Ustaw hasło — to Twój pierwszy login do panelu.',
    'thanks_step_4' => 'Lądujesz w panelu /app — masz 30 dni triala bez ograniczeń.',
    'thanks_no_email' => 'Nie dostałeś maila w 5 minut?',
    'thanks_no_email_help' => 'Sprawdź spam. Jeśli nadal brak — napisz na support@hovera.app, pomożemy.',
];
