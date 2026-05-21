<?php

declare(strict_types=1);

return [
    'meta' => [
        'title' => 'Załóż darmowe konto właściciela konia — Hovera',
        'description' => 'Składaj zamówienia transportu, śledź dokumentację swoich koni, dostawaj oferty od zweryfikowanych przewoźników. Bezpłatnie.',
    ],

    'heading' => 'Załóż konto właściciela konia',
    'subheading' => 'Darmowe konto — bez subskrypcji, bez karty kredytowej. Składaj zlecenia transportu, miej swoje dane w jednym miejscu.',

    'form' => [
        'label' => [
            'owner_name' => 'Imię i nazwisko',
            'owner_email' => 'Email',
            'owner_phone' => 'Telefon (opcjonalnie)',
            'terms' => 'Akceptuję :terms i :privacy',
            'terms_link' => 'regulamin',
            'privacy_link' => 'politykę prywatności',
        ],
        'placeholder' => [
            'owner_name' => 'Jan Kowalski',
            'owner_email' => 'jan@example.com',
            'owner_phone' => '+48 123 456 789',
        ],
        'submit' => 'Załóż konto (bezpłatnie)',
    ],

    'features' => [
        'heading' => 'Co dostaniesz w bezpłatnym koncie',
        'order_transport' => [
            'title' => 'Zamawiaj transport',
            'body' => 'Wystaw zlecenie, dostaniesz oferty od zweryfikowanych przewoźników w okolicy.',
        ],
        'horse_docs' => [
            'title' => 'Dokumenty Twoich koni',
            'body' => 'Paszport, szczepienia, badania weterynaryjne w jednym miejscu, zawsze pod ręką.',
        ],
        'stable_relation' => [
            'title' => 'Twoja stajnia w Hovera.app',
            'body' => 'Jeśli stajnia korzysta z Hovera — opłacaj pensjonat, nadzoruj badania weterynaryjne, wizyty kowala, plany karmienia konia.',
        ],
        'history' => [
            'title' => 'Historia transportów i ofert',
            'body' => 'Wszystkie poprzednie zlecenia, oferty i faktury widoczne w panelu.',
        ],
    ],

    'stable_hint' => [
        'banner' => 'Jeśli Twoja stajnia korzysta już z Hovera.app, po założeniu konta będziesz mógł powiązać konia z pensjonatem — opłacać pobyt, nadzorować badania, wizyty kowala i plany karmienia bezpośrednio z panelu.',
    ],

    'invite' => [
        'banner' => 'Zostałeś zaproszony przez stajnię — po rejestracji Twoje konto zostanie automatycznie powiązane.',
    ],

    'errors' => [
        'provisioning_failed' => 'Nie udało się utworzyć konta — spróbuj ponownie za chwilę lub skontaktuj się z nami.',
        'terms' => 'Musisz zaakceptować regulamin i politykę prywatności.',
    ],

    'thanks' => [
        'heading' => 'Konto utworzone — sprawdź email',
        'body' => 'Wysłaliśmy Ci email z linkiem do ustawienia hasła. Po jego ustawieniu zalogujesz się i zobaczysz swój panel.',
        'next_steps' => 'Po zalogowaniu:',
        'step_horses' => 'Dodaj swoje konie',
        'step_transport' => 'Wystaw pierwsze zlecenie transportu',
        'step_documents' => 'Wgraj dokumenty (paszporty, szczepienia)',
        'open_login' => 'Otwórz logowanie',
        'invite_origin_heading' => 'Stajnia :stable czeka',
        'invite_origin_body' => 'Zostałeś zaproszony przez stajnię „:stable" do uruchomienia pensjonatu. Po zalogowaniu dodaj swojego konia — wtedy stajnia będzie mogła wysłać Ci request o boarding (lub Ty wyślesz pierwszy z panelu).',
    ],
];
