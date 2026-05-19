<?php

declare(strict_types=1);

return [
    'title' => 'Dołącz jako przewoźnik',
    'heading' => 'Dołącz do hovera jako firma transportowa',
    'subtitle' => 'Marketplace transportu koni — zapełnij profil w 10 minut, wgraj dokumenty, '
        .'a my zweryfikujemy konto w 2-3 dni robocze. Klienci znajdą Ciebie w katalogu /przewoznicy.',

    'no_commission_banner' => 'hovera.app nie pobiera prowizji od przewoźników ani od zamawiających — '
        .'tylko subskrypcja miesięczna.',

    'promo' => [
        'heading' => '🎁 Promocja do końca lipca 2026',
        'body' => 'Pełna funkcjonalność. Bez karty. Bez automatycznej zamiany na płatny — '
            .'wybierzesz plan dopiero gdy będziesz pewny że chcesz zostać.',
        'body_yearly' => 'Rejestracja do końca lipca 2026 daje 30 dni za darmo, '
            .'a opłata roczna to 10 × stawka miesięczna (2 miesiące gratis). '
            .'Oferta limitowana czasowo.',
    ],

    'perks' => [
        'title' => 'Co dostajesz?',
        'item_1' => 'Publiczny profil pod app.hovera.app/t/{slug} indeksowany przez Google',
        'item_2' => 'Direct lead od klientów + broadcasting do całej Polski (PWL)',
        'item_3' => 'Kalkulator wyceny + automatyczna oferta PDF + płatność online',
        'item_4' => 'Pierwszy miesiąc gratis od momentu weryfikacji dokumentów',
    ],

    'section' => [
        'company' => 'Dane firmy',
        'owner' => 'Kontakt — właściciel / osoba upoważniona',
        'documents' => 'Dokumenty wymagane',
        'terms' => 'Regulamin i zgody',
    ],

    'field' => [
        'name' => 'Pełna nazwa firmy',
        'name_hint' => 'Tak jak na NIP / CEIDG / KRS.',
        'slug' => 'Adres marketplace (slug)',
        'slug_hint' => 'Tylko małe litery, cyfry i myślniki. Niezmienne po rejestracji. Np. „galoptrans" → app.hovera.app/t/galoptrans',
        'tax_id' => 'NIP',
        'tax_id_hint' => 'Sam numer — 10 cyfr, bez kresek.',
        'regon' => 'REGON',
        'regon_hint' => '9 lub 14 cyfr.',
        'address' => 'Adres siedziby',
        'owner_name' => 'Imię i nazwisko',
        'owner_email' => 'Email kontaktowy',
        'owner_email_hint' => 'Tu wyślemy magic link do panelu po weryfikacji.',
        'owner_phone' => 'Telefon kontaktowy',
    ],

    'documents_disclaimer' => 'Wymagamy 6 dokumentów zgodnie z ustawą PWL (Polska Współpraca '
        .'Logistyczna) i regulaminami hovera. Bez kompletu nie możemy aktywować konta. '
        .'Formaty: PDF, JPG, PNG. Max 5 MB per plik.',

    'documents' => [
        'file_hint' => 'PDF, JPG lub PNG. Max 5 MB.',
        'anonymized_heading' => 'Dokumenty są tylko dla weryfikacji',
        'anonymized_body' => 'Po pozytywnej weryfikacji przez zespół Hovera, dokumenty pokazujemy klientom WYŁĄCZNIE '
            .'w formie zanonimizowanej (bez numerów seryjnych, dat ważności, danych osobowych). '
            .'Publicznie widoczna jest TYLKO informacja: „✓ Pozytywnie zweryfikowany przez zespół Hovera".',
    ],

    'terms' => [
        'marketplace_position' => 'Hovera to platforma marketplace dla firm transportowych. '
            .'Nie jesteśmy przewoźnikiem ani stroną umowy — łączymy klientów i firmy '
            .'transportowe technicznie. Umowa przewozu zawierana jest bezpośrednio między '
            .'Tobą a klientem, pieniądze za transport trafiają na Twoje konto (P24/PayU/Stripe).',
        'accept_html' => 'Akceptuję :regulamin, :marketplace oraz :privacy. Oświadczam, '
            .'że wgrane dokumenty są aktualne i zgodne z prawem.',
        'regulamin' => 'Regulamin hovera',
        'marketplace' => 'Regulamin marketplace',
        'privacy' => 'Politykę prywatności',
    ],

    'submit' => 'Złóż wniosek rejestracyjny',

    'errors' => [
        'heading' => 'Sprawdź formularz:',
        'slug_format' => 'Slug może zawierać tylko małe litery, cyfry i myślniki (np. „galoptrans").',
        'slug_taken' => 'Ten slug jest już zajęty — wybierz inny.',
        'tax_id_format' => 'NIP musi mieć 10 cyfr (bez kresek i spacji).',
        'regon_format' => 'REGON musi mieć 9 lub 14 cyfr.',
        'terms' => 'Musisz zaakceptować regulamin żeby kontynuować.',
        'provisioning_failed' => 'Niestety nie udało się utworzyć konta — spróbuj ponownie '
            .'za chwilę. Jeśli problem się powtarza, napisz do office@hovera.app.',
    ],

    'notify' => [
        'thanks_silent' => 'Dziękujemy — sprawdzimy zgłoszenie i odezwiemy się.',
    ],

    'thanks' => [
        'title' => 'Dziękujemy za rejestrację',
        'heading' => 'Zgłoszenie przyjęte!',
        'intro' => 'Konto firmy „:name" zostało utworzone i czeka na weryfikację dokumentów.',
        'step_1' => 'Zweryfikujemy Twoje dokumenty w ciągu 2-3 dni roboczych.',
        'step_2' => 'Po pozytywnej weryfikacji wyślemy magic link do panelu na Twój email.',
        'step_3' => 'Wtedy uruchomi się 1 miesiąc trial — będziesz mógł dodać pojazdy, kierowców i przyjmować zamówienia.',
        'contact_hint' => 'Masz pytanie? Napisz na :email.',
        'cta_directory' => 'Zobacz katalog przewoźników',
    ],
];
