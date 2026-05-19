<?php

declare(strict_types=1);

return [
    'title' => 'Załóż konto',
    'title_stable' => 'Załóż stajnię',
    'title_transporter' => 'Załóż firmę transportową',
    'thanks_title' => 'Sprawdź mail',

    'heading' => 'Załóż konto w hovera',
    'heading_stable' => 'Załóż stajnię w hovera',
    'heading_transporter' => 'Załóż firmę transportową w hovera',
    'subtitle' => 'Wypełnij 4 pola, dostaniesz mail z linkiem do ustawienia hasła. Bez karty kredytowej.',
    'subtitle_stable' => 'Wypełnij 4 pola, dostaniesz mail z linkiem do ustawienia hasła. Bez karty kredytowej.',
    'subtitle_transporter' => 'Wypełnij 4 pola, dostaniesz mail z linkiem do ustawienia hasła. 30 dni triala — plan Pro (5 pojazdów, 10 kierowców).',
    'back_to_choose' => 'wróć do wyboru typu konta',

    'choose' => [
        'title' => 'Co prowadzisz?',
        'heading' => 'Co prowadzisz?',
        'subtitle' => 'Hovera obsługuje dwa zupełnie różne biznesy w jednym ekosystemie. Wybierz właściwy dla siebie — możesz później dodać drugi.',
        'stable' => [
            'title' => 'Prowadzę stajnię',
            'price' => 'od 0 zł / mc · 30 dni triala',
            'bullet_1' => 'Multi-resource kalendarz: lekcje, treningi, opieka',
            'bullet_2' => 'Klienci + karnety + auto-rozliczenia',
            'bullet_3' => 'Karta konia, dziennik zdrowia, plan żywienia',
            'bullet_4' => 'Faktury VAT + KSeF + portal właściciela',
            'cta' => 'Zarejestruj stajnię →',
        ],
        'transporter' => [
            'title' => 'Prowadzę firmę transportową',
            'price' => 'od 149 zł / mc · 30 dni triala',
            'bullet_1' => 'Pojazdy + kierowcy + cennik km/paliwo',
            'bullet_2' => 'Kalkulator tras z mapą (ORS/Mapbox/Google)',
            'bullet_3' => 'Oferty PDF + numeracja + mailing',
            'bullet_4' => 'Marketplace zapytań od stajni',
            'cta' => 'Zarejestruj firmę →',
        ],
    ],

    'trial_strong' => '🎉 30 dni za darmo',
    'trial_text' => 'Pełna funkcjonalność. Bez karty. Bez automatycznej zamiany na płatny — wybierzesz plan dopiero gdy będziesz pewny że chcesz zostać.',

    'label' => [
        'name' => 'Nazwa stajni',
        'name_stable' => 'Nazwa stajni',
        'name_transporter' => 'Nazwa firmy',
        'slug' => 'Adres URL stajni',
        'slug_transporter' => 'Adres URL firmy transportowej',
        'owner_name' => 'Twoje imię i nazwisko',
        'owner_email' => 'E-mail',
        'terms' => 'Akceptuję <a href="/regulamin" target="_blank">regulamin</a> i <a href="/polityka-prywatnosci" target="_blank">politykę prywatności</a>',
        // Dodatkowy fragment dorzucany do labelki dla type=transporter — informuje
        // o akceptacji odrębnego regulaminu marketplace (Hovera = pośrednik).
        'terms_marketplace_suffix' => ' oraz <a href="/regulamin-marketplace" target="_blank">regulamin marketplace transportowego</a> (Hovera świadczy usługi pośrednictwa technologicznego — nie jest przewoźnikiem ani stroną umowy przewozu)',
    ],

    'placeholder' => [
        'name' => 'Stajnia Pegaz',
        'owner_name' => 'Anna Kowalska',
    ],

    'helper' => [
        'name' => 'Tak będzie wyświetlane w panelu i na publicznej stronie.',
        'slug' => 'Same małe litery, cyfry i myślniki. Min. 3 znaki.',
        'slug_transporter' => 'Same małe litery, cyfry i myślniki. Min. 3 znaki. Profil firmy będzie pod tym adresem w marketplace.',
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
        'provisioning_failed' => 'Coś poszło nie tak po naszej stronie. Spróbuj ponownie za chwilę albo napisz na office@hovera.app.',
    ],

    'thanks_heading' => '✓ Konto założone',
    'thanks_subtitle' => 'Stajnia :tenant została utworzona. Wysłaliśmy mail z linkiem do ustawienia hasła.',
    'thanks_step_1' => 'Sprawdź skrzynkę odbiorczą (link ważny 7 dni).',
    'thanks_step_2' => 'Kliknij „Akceptuj zaproszenie" w mailu.',
    'thanks_step_3' => 'Ustaw hasło — to Twój pierwszy login do panelu.',
    'thanks_step_4' => 'Lądujesz w panelu /app — masz 30 dni triala bez ograniczeń.',
    'thanks_no_email' => 'Nie dostałeś maila w 5 minut?',
    'thanks_no_email_help' => 'Sprawdź spam. Jeśli nadal brak — napisz na office@hovera.app, pomożemy.',
];
