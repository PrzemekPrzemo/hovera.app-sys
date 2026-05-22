<?php

declare(strict_types=1);

return [
    // Wspólne elementy używane w wielu notyfikacjach.
    'common' => [
        'greeting' => 'Cześć!',
        'greeting_named' => 'Cześć :name!',
        'salutation_prefix' => '— ',
        'field' => [
            'term' => 'Termin',
            'instructor' => 'Instruktor',
            'horse' => 'Koń',
            'arena' => 'Ujeżdżalnia',
            'address' => 'Adres',
            'phone' => 'Telefon do stajni',
            'old_date' => 'Stara data',
            'new_date' => 'Nowa data',
            'cancelled_term' => 'Anulowany termin',
            'from' => 'Od',
            'subject' => 'Temat',
            'issued_at' => 'Data wystawienia',
            'gross_amount' => 'Kwota brutto',
            'due_date' => 'Termin płatności',
            'client' => 'Klient',
            'client_note' => 'Notatka klienta',
        ],
        'duration_minutes' => ':minutes min',
        'cancel_action' => 'Odwołaj rezerwację',
        'cancel_policy' => 'Jeśli musisz odwołać, kliknij poniżej. Odwołanie minimum :hours godzin przed lekcją jest bez kosztu.',
        'portal_link' => 'Wszystkie rezerwacje znajdziesz w panelu klienta: [:url](:url)',
    ],

    'booking_confirmed' => [
        'subject' => 'Rezerwacja potwierdzona — :tenant',
        'line_intro' => 'Twoja rezerwacja w **:tenant** została potwierdzona.',
        'line_signoff' => 'Do zobaczenia!',
    ],

    'booking_cancelled' => [
        'subject' => 'Rezerwacja odwołana — :tenant',
        'line_by_client' => 'Twoja rezerwacja w **:tenant** została odwołana zgodnie z Twoim wnioskiem.',
        'line_by_stable' => 'Stajnia **:tenant** odwołała Twoją rezerwację. Skontaktuj się ze stajnią po szczegóły.',
        'pass_restored' => 'Karnet został zwrócony — możesz go wykorzystać przy kolejnej rezerwacji.',
        'pass_not_restored' => 'Karnet (jeśli był używany) nie został zwrócony — odwołanie po terminie polityki.',
    ],

    'booking_reminder' => [
        'subject' => 'Przypomnienie: jutro :time — :tenant',
        'line_intro' => 'Przypominamy o jutrzejszej rezerwacji.',
        'cancel_policy' => 'Jeśli musisz odwołać, zrób to jak najszybciej — odwołanie do :hours godzin przed lekcją jest bez kosztu.',
        'line_signoff' => 'Do zobaczenia jutro!',
    ],

    'booking_requested' => [
        'subject' => 'Otrzymaliśmy zgłoszenie — :tenant',
        'line_intro' => 'Dziękujemy za zgłoszenie rezerwacji w stajni **:tenant**.',
        'line_processing' => 'Stajnia potwierdzi rezerwację mailem (zwykle w ciągu kilku godzin) i przydzieli konia.',
        'line_pass_warning' => 'Jeśli nie odwołasz w terminie, karnet (jeśli używany) zostanie zużyty.',
    ],

    'booking_rescheduled' => [
        'subject' => 'Rezerwacja przesunięta — :tenant',
        'line_intro' => 'Twoja rezerwacja w **:tenant** została przesunięta.',
        'line_undo' => 'Jeśli to przesunięcie było pomyłką, możesz odwołać i zarezerwować nowy termin.',
        'portal_link' => 'Zarządzaj rezerwacjami w panelu klienta: [:url](:url)',
    ],

    'client_portal_magic_link' => [
        'subject' => 'Logowanie do panelu — :tenant',
        'line_intro' => 'Klikinij poniżej, aby zalogować się do panelu klienta **:tenant**.',
        'action' => 'Zaloguj się',
        'line_ttl' => 'Link działa przez :minutes minut i można użyć go tylko raz.',
        'line_security' => 'Jeśli to nie Ty próbujesz się zalogować — zignoruj tę wiadomość.',
    ],

    'horse_message' => [
        'subject_default' => 'Nowa wiadomość — :horse — :tenant',
        'subject_with_subject' => ':subject (:horse)',
        'line_intro' => 'Otrzymałeś nową wiadomość dotyczącą konia **:horse** (:tenant).',
        'attachments_one' => '📎 1 załącznik',
        'attachments_many' => '📎 :count załączniki',
        'action' => 'Otwórz wiadomość',
    ],

    'owner_message_to_stable' => [
        'subject_default' => 'Nowa wiadomość od właściciela — :horse',
        'subject_with_subject' => ':subject (:horse)',
        'line_intro' => 'Właściciel **:owner** wysłał wiadomość dotyczącą konia **:horse**.',
        'attachment_count' => '📎 Załączników: :count',
        'action' => 'Zobacz konia w panelu',
    ],

    'invoice_issued' => [
        'subject' => ':kind :number — :tenant',
        'line_intro' => 'Wystawiliśmy :kind **:number** ze stajni **:tenant**.',
        'action_pay' => 'Zobacz fakturę i zapłać',
        'action_view' => 'Zobacz fakturę',
        'line_offline_payment' => 'Płatność prosimy uregulować przelewem na konto stajni — szczegóły w panelu klienta.',
        'line_thanks' => 'Dziękujemy!',
    ],

    'new_booking_request' => [
        'subject' => 'Nowe zgłoszenie online — :tenant',
        'line_intro' => 'Klient zgłosił prośbę o lekcję w stajni **:tenant**:',
        'client_format' => ':name (:email)',
        'client_format_with_phone' => ':name (:email, tel. :phone)',
        'line_action_required' => 'Aby zatwierdzić, przejdź do edycji rezerwacji, przypisz konia i zmień status na „Potwierdzone".',
        'action' => 'Otwórz rezerwację',
        'line_horse_assignment' => 'Konia można przypisać dopiero w momencie potwierdzania — system wymaga tego przed zmianą statusu.',
        'salutation' => '— Hovera',
    ],

    'user_invitation' => [
        'subject_with_tenant' => 'Zaproszenie do stajni :tenant — Hovera',
        'subject_default' => 'Zaproszenie do Hovera',
        'line_with_tenant' => 'Zostałeś dodany do stajni **:tenant** w systemie Hovera:role.',
        'line_with_tenant_role' => ' z rolą *:role*',
        'line_default' => 'Otrzymałeś zaproszenie do systemu Hovera.',
        'line_setup' => 'Aby aktywować konto i ustawić hasło, kliknij poniżej.',
        'action' => 'Ustaw hasło i zaloguj się',
        'line_expires' => 'Link wygasa :date (UTC).',
        'line_security' => 'Jeśli to nie Ty, możesz zignorować tę wiadomość — bez kliknięcia konto nie zostanie aktywowane.',
        'salutation' => '— Hovera',
    ],

    // Faza 6 PR 6.1 — Owner notifications hub (database + mail) gdy
    // stajnia wykonuje akcję dotyczącą konia ownera.
    'owner_new_message' => [
        'subject_default' => 'Nowa wiadomość — :horse — :stable',
        'subject_with_subject' => ':subject (:horse)',
        'line_intro' => 'Stajnia **:stable** wysłała wiadomość dotyczącą konia **:horse**.',
        'attachment_count' => '📎 Załączników: :count',
        'action' => 'Otwórz wiadomość',
    ],

    'owner_new_invoice' => [
        'subject_default' => 'Nowa faktura — :stable',
        'subject_with_number' => 'Faktura :number — :stable',
        'line_intro' => 'Stajnia **:stable** wystawiła nową fakturę.',
        'field' => [
            'number' => 'Numer',
            'period' => 'Okres rozliczeniowy',
            'horse' => 'Koń',
            'total' => 'Razem (brutto)',
            'due_at' => 'Termin płatności',
        ],
        'action' => 'Zobacz fakturę',
    ],

    'owner_quote_sent' => [
        'subject' => 'Nowa oferta transportu — :transporter',
        'line_intro' => 'Przewoźnik **:transporter** wysłał Ci ofertę na Twoje zapytanie transportowe.',
        'field' => [
            'route' => 'Trasa',
            'price' => 'Cena (brutto)',
            'date' => 'Proponowany termin',
        ],
        'action_accept' => 'Otwórz ofertę',
        'line_panel' => 'Możesz też porównać oferty w panelu: :url',
    ],

    'owner_vet_visit' => [
        'subject' => ':horse — :type',
        'line_intro' => 'Stajnia **:stable** zarejestrowała :type dla konia **:horse**.',
        'field' => [
            'cost' => 'Koszt',
            'next_due' => 'Kolejny termin',
        ],
        'action' => 'Zobacz oś czasu',
    ],

    'boarding_requested' => [
        'subject' => 'Stajnia :stable zaprasza :horse do pensjonatu',
        'line_intro' => 'Stajnia **:stable** chce zacząć pensjonat dla konia **:horse**. Czeka na Twoją zgodę.',
        'line_action' => 'Otwórz panel właściciela, sprawdź szczegóły i zaakceptuj lub odrzuć zaproszenie.',
        'action' => 'Zobacz zaproszenia',
    ],

    'boarding_accepted' => [
        'subject' => ':horse zaakceptowany do pensjonatu',
        'line_intro' => 'Właściciel **:owner** zaakceptował pensjonat dla konia **:horse** w Twojej stajni.',
        'line_next_step' => 'Koń pojawił się w liście — możesz teraz przypisać go do boksu i ustawić cennik usług.',
        'action' => 'Zobacz konia',
    ],

    'boarding_rejected' => [
        'subject' => ':horse — pensjonat odrzucony',
        'line_intro' => 'Właściciel **:owner** odrzucił prośbę o pensjonat dla konia **:horse** z powodem:',
        'line_contact' => 'Skontaktuj się bezpośrednio z właścicielem (:email) jeśli sytuacja wymaga wyjaśnienia.',
    ],
];
