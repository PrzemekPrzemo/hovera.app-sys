<?php

declare(strict_types=1);

return [
    'navigation' => [
        'label' => 'Subskrypcja hovera',
    ],
    'page' => [
        'title' => 'Subskrypcja hovera',
        'subtitle' => 'Wybierz plan dla stajni :stable. Płatność cykliczna kartą — możesz anulować w każdej chwili.',
        'redirecting' => 'Przekierowanie do strony rozliczeń…',
        'click_here' => 'Jeśli przeglądarka nie przeniosła automatycznie — kliknij tutaj.',
    ],
    'status' => [
        'active' => 'Subskrypcja aktywna',
        'trial_expired' => 'Trial wygasł — wybierz plan',
        'trial_days_left' => '{1} :days dzień triala|[2,4] :days dni triala|[5,*] :days dni triala',
    ],
    'period' => [
        'label' => 'Okres rozliczeniowy',
        'monthly' => 'Miesięcznie',
        'yearly' => 'Rocznie (-10%)',
        'month_short' => 'mies.',
        'year_short' => 'rok',
    ],
    'actions' => [
        'choose' => 'Wybierz plan',
        'current' => 'Twój aktualny plan',
        'manage' => 'Zarządzaj subskrypcją',
        'back_to_app' => 'Powrót do aplikacji',
    ],
    'manage' => [
        'title' => 'Zarządzanie subskrypcją',
        'description' => 'Zmień kartę, pobierz faktury lub anuluj subskrypcję w portalu Stripe.',
    ],
    'return' => [
        'title' => 'Subskrypcja',
        'success_title' => 'Subskrypcja aktywna',
        'success_body' => 'Dziękujemy! Twoja subskrypcja hovera została aktywowana — fakturę dostaniesz mailem.',
        'go_to_app' => 'Przejdź do aplikacji',
        'pending_title' => 'Przetwarzamy płatność',
        'pending_body' => 'Stripe potwierdza płatność — może to potrwać kilka sekund. Odśwież stronę za chwilę.',
        'refresh' => 'Odśwież',
    ],
    'errors' => [
        'unknown_plan' => 'Wybrany plan nie istnieje lub jest nieaktywny.',
        'checkout_failed' => 'Nie udało się utworzyć sesji płatności. Spróbuj ponownie albo skontaktuj się z nami.',
        'portal_failed' => 'Nie udało się otworzyć portalu rozliczeniowego. Skontaktuj się z nami.',
    ],
    'footer' => [
        'disclaimer' => 'Płatności obsługuje Stripe. Twoje dane karty nie są przechowywane na serwerach hovera. Faktury VAT generujemy automatycznie po każdym pomyślnym rozliczeniu.',
    ],
];
