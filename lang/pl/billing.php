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
        'one_time' => 'Jednorazowo',
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
    'suggested_badge' => 'Rekomendowany',
    'trial_banner' => [
        'expires_today' => 'Twój trial kończy się dziś.',
        'expires_tomorrow' => 'Trial kończy się jutro.',
        'days_left' => '{1} :days dzień triala pozostało.|[2,4] :days dni triala pozostało.|[5,*] :days dni triala pozostało.',
        'pro_pitch' => 'Masz pełną funkcjonalność Pro, ale w trialu limit to :horses koni i :clients klientów. Po wyborze planu Pro — bez limitu.',
        'cta_pro' => 'Wybierz Pro',
    ],
    'limits' => [
        'title' => 'Limit planu osiągnięty',
        'horses_exceeded' => 'Trial: limit :limit koni — wybierz plan aby dodać więcej.',
        'clients_exceeded' => 'Trial: limit :limit klientów — wybierz plan aby dodać więcej.',
        'vehicles_exceeded' => 'Limit :limit pojazdów w aktualnym planie — wybierz wyższy plan aby dodać więcej.',
        'drivers_exceeded' => 'Limit :limit kierowców w aktualnym planie — wybierz wyższy plan aby dodać więcej.',
    ],
    'onboarding_fee' => [
        'label' => 'Opłata wdrożeniowa — plan :plan',
        'description' => 'Jednorazowa opłata aktywacyjna doliczana przy starcie subskrypcji.',
    ],

    'payment_method' => [
        'label' => 'Metoda płatności',
        'stripe' => 'Karta — Stripe',
        'stripe_hint' => 'Międzynarodowe karty, EUR/PLN, wygodny portal samoobsługowy.',
        'payu' => 'PayU (karta + BLIK + przelew)',
        'payu_hint' => 'Polskie metody płatności, niższa prowizja, szybki BLIK.',
    ],

    'payu' => [
        'card' => [
            'heading' => 'Twoja karta (PayU)',
            'brand_mask' => ':brand :mask',
            'expires' => 'Wygasa: :expires',
            'no_expiry' => 'Wygasa: nieznane',
            'cancel_cta' => 'Anuluj odnawianie',
            'cancel_confirm' => 'Na pewno anulować? Dostęp masz do końca opłaconego okresu, ale po tej dacie subskrypcja wygaśnie i będziesz musiał wybrać plan ponownie.',
        ],
        'cancel_success' => 'Anulowano. Karta usunięta — dostęp do końca opłaconego okresu, potem subskrypcja wygaśnie.',
        'status' => [
            'past_due' => 'Płatność nie powiodła się — sprawdź kartę',
        ],
    ],
    'onboarding_fee_label' => 'jednorazowo (opłata wdrożeniowa)',
    'vat_notice' => 'Ceny netto. Do każdej kwoty doliczamy 23% VAT.',
    'vat_notice_short' => '+ 23% VAT',
    'email' => [
        'invoice_paid' => [
            'subject' => 'Faktura :number — opłacona, dziękujemy!',
            'heading' => 'Faktura :number opłacona',
            'intro' => 'Dziękujemy! Otrzymaliśmy płatność za subskrypcję hovera dla stajni :stable.',
            'field_number' => 'Numer faktury',
            'field_plan' => 'Plan',
            'field_period' => 'Okres',
            'field_total' => 'Kwota brutto',
            'field_paid_at' => 'Opłacono',
            'pdf_pending' => 'Plik PDF z fakturą wkrótce pojawi się w panelu rozliczeń. Faktura zostanie też wysłana do KSeF (jeśli skonfigurowane).',
            'cta_billing' => 'Otwórz panel rozliczeń',
            'thanks' => 'Cieszymy się, że zostajesz z nami!',
            'signoff' => 'Pozdrawiamy,',
        ],
        'payu_charge_failed' => [
            'subject' => 'Nie udało się pobrać opłaty subskrypcji — :stable',
            'greeting' => 'Cześć!',
            'intro' => 'Nie udało nam się pobrać cyklicznej opłaty za subskrypcję :plan w stajni :stable.',
            'attempt' => 'To :attempts próba pobrania. Spróbujemy ponownie za :next_in_days dni.',
            'fix' => 'Najczęstsze przyczyny: niewystarczające środki, karta wygasła lub blokada przez bank. Sprawdź kartę w panelu rozliczeń, żeby uniknąć zawieszenia subskrypcji.',
            'cta' => 'Sprawdź metodę płatności',
            'signoff' => '— Hovera',
        ],
        'payu_subscription_suspended' => [
            'subject' => 'Subskrypcja zawieszona — :stable',
            'greeting' => 'Cześć!',
            'intro' => 'Niestety, mimo 3 prób nie udało się pobrać opłaty za subskrypcję :plan dla stajni :stable.',
            'consequence' => 'Subskrypcja została zawieszona. Twoje dane są bezpieczne, ale dostęp do funkcji premium jest wstrzymany do momentu opłacenia nowej subskrypcji.',
            'cta' => 'Wznów subskrypcję',
            'signoff' => '— Hovera',
        ],
    ],
];
