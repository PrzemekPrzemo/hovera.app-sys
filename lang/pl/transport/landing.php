<?php

declare(strict_types=1);

return [
    'title' => 'Oferta transportowa :number',
    'quote_number' => 'NUMER OFERTY',
    'accepted_banner' => 'Dziękujemy! Oferta zaakceptowana — przewoźnik się skontaktuje.',
    'rejected_banner' => 'Oferta odrzucona. Dziękujemy za informację.',
    'already_accepted' => 'Ta oferta została już zaakceptowana.',
    'already_rejected' => 'Ta oferta została wcześniej odrzucona.',

    'label' => [
        'from' => 'Skąd',
        'to' => 'Dokąd',
        'date' => 'Data',
        'distance' => 'Dystans',
        'valid_until' => 'Ważne do',
        'net' => 'Netto',
        'vat' => 'VAT (:rate%)',
        'gross' => 'Razem do zapłaty',
    ],

    'action' => [
        'accept' => 'Akceptuję ofertę',
        'reject' => 'Odrzucam',
    ],

    'company' => [
        'heading' => 'Faktura',
        'choose_label' => 'Faktura zostanie wystawiona na:',
        'as_private' => 'Osoba prywatna',
        'as_company' => 'Firma (FV firmowa)',
        'company_name_label' => 'Nazwa firmy',
        'tax_id_label' => 'NIP',
        'tax_id_placeholder' => '1234567890',
        'address_label' => 'Adres firmy (ulica, kod, miasto)',
        'address_placeholder' => 'np. ul. Marszałkowska 1, 00-001 Warszawa',
        'lookup_action' => 'Pobierz dane z GUS',
        'lookup_loading' => 'Pobieram…',
        'lookup_success' => 'Dane pobrane (:sources). Sprawdź i zaktualizuj jeśli potrzeba.',
        'lookup_not_found' => 'Nie znaleziono firmy o tym NIP. Wpisz dane ręcznie.',
        'invalid_nip' => 'NIP jest niepoprawny (10 cyfr + suma kontrolna).',
        'lookup_error' => 'Błąd pobierania danych. Wpisz dane ręcznie.',
    ],

    'payment' => [
        'heading' => 'Płatność',
        'disclaimer' => 'Płatność realizowana BEZPOŚREDNIO do :transporter. Hovera jest pośrednikiem marketplace i NIE przyjmuje płatności. Reklamacje płatności kieruj bezpośrednio do przewoźnika.',
        'confirmed' => 'Płatność potwierdzona przez przewoźnika (:date)',
        'pay_now' => 'Zapłać teraz (:amount :currency)',
        'instructions_heading' => 'Instrukcje płatności:',
        'contact_transporter' => 'Skontaktuj się z :transporter aby uzgodnić sposób płatności.',
    ],

    'footer' => 'Bezpieczna strona obsługiwana przez :app',

    // KRYTYCZNY disclaimer wyświetlany ZAWSZE nad przyciskami akceptacji/odrzucenia.
    // Akceptacja oferty = zawarcie umowy z PRZEWOŹNIKIEM (nie Hovera). Wymagane
    // przez Regulamin marketplace transportowego §6. HTML — strong + link.
    // Parametry :transporter_name (escaped) i :transporter_nip (np. "NIP: 1234567890" lub pusty).
    'disclaimer_intermediary_html' => '<strong>Akceptując ofertę zawierasz umowę BEZPOŚREDNIO z :transporter_name :transporter_nip.</strong> Hovera jest pośrednikiem marketplace — NIE jest stroną tej umowy, NIE jest przewoźnikiem i NIE odpowiada za realizację transportu. Zapoznaj się z <a href="/regulamin-marketplace" target="_blank" style="color:inherit;text-decoration:underline;">regulaminem marketplace transportowego</a>.',
];
