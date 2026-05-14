<?php

declare(strict_types=1);

return [
    'profile' => [
        'navigation' => 'Profil',
        'title' => 'Twój profil',
    ],

    'calendar' => [
        'navigation' => 'Plan dnia',
    ],

    'tenant_settings' => [
        'navigation' => 'Ustawienia stajni',
        'title' => 'Ustawienia stajni',
    ],

    'invoicing_settings' => [
        'navigation' => 'Faktury i rozliczenia',
        'title' => 'Faktury i rozliczenia',
    ],

    'payment_settings' => [
        'navigation' => 'Płatności online',
        'title' => 'Płatności online',
    ],

    'ksef_settings' => [
        'navigation' => 'KSeF (e-faktury)',
        'title' => 'KSeF — krajowy system e-faktur',
    ],

    'company_lookup' => [
        'navigation' => 'GUS / KRS',
        'title' => 'Weryfikacja firm — GUS / KRS',
    ],

    'my_tasks' => [
        'navigation' => 'Moje zadania',
        'title' => 'Moje zadania',
        'signed_in_as' => 'Zalogowany jako specjalista',
        'sections' => [
            'overdue' => 'Przeterminowane',
            'upcoming' => 'Najbliższe zabiegi (30 dni)',
            'recent' => 'Ostatnio wykonane (30 dni)',
        ],
        'empty' => [
            'overdue' => 'Brak zaległych zadań — gratulacje!',
            'upcoming' => 'Brak zaplanowanych zabiegów w najbliższych 30 dniach.',
            'recent' => 'Brak wpisów z ostatnich 30 dni.',
        ],
        'overdue_by_days' => '{1} przeterminowane o 1 dzień|[2,4] przeterminowane o :days dni|[5,*] przeterminowane o :days dni',
        'in_days' => '{0} dziś|{1} jutro|[2,*] za :days dni',
    ],

    'help' => [
        'navigation' => 'Pomoc',
        'title' => 'Centrum pomocy',
        'tab' => [
            'manual' => 'Instrukcja obsługi',
            'legal' => 'Dokumentacja prawna',
        ],
        'persona' => [
            'owner' => 'Właściciel / admin',
            'owner_desc' => 'Pełen panel, finanse, zespół, ustawienia stajni.',
            'employee' => 'Pracownik / instruktor',
            'employee_desc' => 'Kalendarz, klienci, konie — codzienna operacja.',
            'specialist' => 'Weterynarz / specjalista',
            'specialist_desc' => 'Karty zdrowia, wizyty, leczenie koni.',
            'client' => 'Klient stajni',
            'client_desc' => 'Portal: rezerwacje, karnety, mój koń.',
        ],
        'legal' => [
            'open_in_new_tab' => 'Otwórz wersję publiczną',
        ],
        'topbar' => [
            'help' => 'Centrum pomocy',
            'report_bug' => 'Zgłoś błąd / sugestię',
        ],
        'public_lead' => 'Instrukcje obsługi per rola oraz pełna dokumentacja prawna hovera. Dostępne bez logowania, w 5 językach.',
        'public_cta' => 'Chcesz wypróbować hovera w swojej stajni? 30 dni za darmo, bez karty.',
        'public_meta_desc' => 'Instrukcje obsługi, regulamin, polityka prywatności i DPA hovera — systemu do zarządzania stajnią jeździecką.',
        'bug_report' => [
            'title' => 'Zgłoś błąd lub sugestię',
            'lead' => 'Twoje zgłoszenie trafi bezpośrednio do zespołu hovera w Todoist — wraz z URL strony, na której jesteś.',
            'kind_label' => 'Rodzaj',
            'kind_bug' => 'Błąd',
            'kind_idea' => 'Sugestia / zmiana',
            'subject_label' => 'Krótki tytuł',
            'subject_placeholder' => 'np. Nie da się usunąć karnetu',
            'description_label' => 'Opis',
            'description_placeholder' => 'Co się wydarzyło? Co powinno się wydarzyć? Kroki do powtórzenia.',
            'screenshot_label' => 'Zrzut ekranu (PNG/JPG, opcjonalnie)',
            'submit' => 'Wyślij zgłoszenie',
            'cancel' => 'Anuluj',
            'success' => 'Dziękujemy — zgłoszenie zostało wysłane.',
            'error' => 'Nie udało się wysłać. Spróbuj ponownie lub napisz na support@hovera.app.',
        ],
    ],

    'reports' => [
        'month_picker' => 'Miesiąc',
        'apply' => 'Pokaż',
        'empty' => 'Brak danych dla wybranego miesiąca.',
        'col_item' => 'Pozycja',
        'col_total' => 'Wartość netto',

        'revenue' => [
            'navigation' => 'Przychody',
            'title' => 'Raport miesięczny — przychody',
            'total_heading' => 'Razem netto · :month',
            'invoice_count' => 'Faktury w okresie: :count',
            'top_items' => 'Top 10 pozycji',
            'bucket' => [
                'boarding' => 'Pensjonat',
                'lessons' => 'Lekcje',
                'passes' => 'Karnety',
                'other' => 'Inne',
            ],
        ],

        'aging' => [
            'navigation' => 'Wiekowanie należności',
            'title' => 'Wiekowanie należności',
            'total_heading' => 'Łącznie zaległe',
            'list_heading' => 'Lista przeterminowanych faktur',
            'empty' => 'Brak zaległych faktur — wszystko opłacone.',
            'col_invoice' => 'Numer FV',
            'col_client' => 'Klient',
            'col_due_at' => 'Termin',
            'col_days_overdue' => 'Dni po terminie',
            'col_amount' => 'Kwota brutto',
            'days' => 'dni',
            'bucket' => [
                '0_30' => '1–30 dni',
                '31_60' => '31–60 dni',
                '61_90' => '61–90 dni',
                '90_plus' => '> 90 dni',
            ],
        ],

        'horse_utilization' => [
            'navigation' => 'Wykorzystanie konia',
            'title' => 'Wykorzystanie konia',
            'heading' => 'Lekcje per koń · :month',
            'subtitle' => 'Liczba potwierdzonych / zakończonych rezerwacji w wybranym miesiącu. Powyżej 25 lekcji = ryzyko przeciążenia.',
            'col_horse' => 'Koń',
            'col_lessons' => 'Lekcje',
            'col_hours' => 'Godziny',
        ],

        'instructor_utilization' => [
            'navigation' => 'Wykorzystanie instruktora',
            'title' => 'Wykorzystanie instruktora',
            'heading' => 'Godziny i frekwencja · :month',
            'col_instructor' => 'Instruktor',
            'col_lessons' => 'Lekcje',
            'col_hours' => 'Godziny',
            'col_cancelled' => 'Odwołane',
            'col_no_show' => 'No-show',
            'col_attendance' => 'Frekwencja',
        ],
    ],

    'bulk_invoicing' => [
        'navigation' => 'Masowe FV za miesiąc',
        'title' => 'Bulk invoicing — masowe FV za pensjonat',
        'month_picker' => 'Miesiąc do rozliczenia',
        'refresh' => 'Odśwież podgląd',
        'helper' => 'Generuje wersję roboczą faktury (Draft) dla każdego klienta na podstawie aktywnych usług pensji jego koni. Karnety są fakturowane przy sprzedaży i nie wchodzą w bulk. Każdą Draft fakturę zatwierdzasz osobno w Faktury → Wystaw.',
        'preview_heading' => 'Podgląd · :month · :count klientów',
        'empty' => 'Brak naliczeń dla wybranego miesiąca. Sprawdź czy konie mają przypisane usługi pensji aktywne w danym okresie.',
        'items_suffix' => 'pozycji',
        'col_item' => 'Pozycja',
        'col_qty' => 'Ilość',
        'col_unit_price' => 'Cena/jedn.',
        'col_net' => 'Netto',
        'col_gross' => 'Brutto',
        'totals' => 'Razem (zaznaczone lub wszyscy):',
        'net_short' => 'netto',
        'gross_short' => 'brutto',
        'actions' => [
            'generate' => 'Generuj Drafty',
        ],
        'confirm' => [
            'heading' => 'Wygenerować Drafty FV?',
            'description' => 'Stworzymy wersje robocze faktur za :month dla zaznaczonych klientów (lub wszystkich z podglądu). Każdą zatwierdzisz osobno w Faktury.',
            'submit' => 'Tak, generuj',
        ],
        'flash' => [
            'success' => 'Wygenerowano :count Draftów. Sprawdź zakładkę Faktury aby je wystawić.',
        ],
    ],
];
