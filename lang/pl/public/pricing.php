<?php

declare(strict_types=1);

return [
    'title' => 'Cennik',
    'meta_description' => 'Przejrzysty cennik hovera — od 0 zł za stajnię do 5 koni, do 499 zł za nieograniczony plan Pro. 30 dni za darmo bez karty.',

    'heading' => 'Cennik bez gwiazdek',
    'lede' => 'Wybierz plan dopasowany do wielkości stajni. Bez ukrytych opłat, bez podpisywania umów, bez karty na start.',
    'differentiator' => 'Konkurencja ukrywa cennik za formularzem. My pokazujemy.',

    'billing_label' => 'Okres rozliczeniowy',
    'monthly' => 'Miesięcznie',
    'yearly' => 'Rocznie',
    'save_yearly' => 'Oszczędź ~10% rocznie',
    'most_popular' => 'Najpopularniejszy',
    'month_short' => 'mies.',
    'free_forever' => 'Bezpłatnie na zawsze, bez karty.',
    'billed_monthly' => 'Rozliczane miesięcznie.',
    'billed_yearly_total' => 'Rozliczane rocznie · :total zł / rok',
    'custom_price' => 'Wycena indywidualna',
    'custom_price_note' => 'Skontaktujemy się w 24h.',
    'unlimited' => 'bez limitu',

    'tagline' => [
        'free' => 'Dla pojedynczego instruktora — sprawdź czy hovera Ci pasuje.',
        'solo' => 'Dla solowego instruktora z karnetami i online booking.',
        'stable' => 'Dla małej i średniej stajni z fakturami i KSeF.',
        'pro' => 'Dla pensjonatów i większych stajni — bez limitu klientów.',
        'enterprise' => 'Dla sieci stajni i franczyz — white-label, SSO, SLA.',
    ],

    'cta' => [
        'start_free' => 'Zacznij za darmo',
        'start_trial' => 'Zacznij 30 dni za darmo',
        'contact' => 'Porozmawiajmy',
    ],

    'compare' => [
        'heading' => 'Porównaj plany',
        'sub' => 'Każdy plan zawiera wszystko z poprzedniego — różnica to dodatkowe limity i moduły.',
        'feature' => 'Funkcjonalność',
        'support_level' => 'Wsparcie',
        'group' => [
            'limits' => 'Limity',
            'core' => 'Funkcje',
            'support' => 'Wsparcie i SLA',
        ],
        'limits' => [
            'max_horses' => 'Liczba koni',
            'max_clients' => 'Liczba klientów',
            'max_users' => 'Pracownicy w panelu',
            'max_storage_mb' => 'Przestrzeń na zdjęcia/dokumenty',
        ],
        'features' => [
            'multi_calendar' => 'Multi-resource kalendarz (instruktor, koń, sala)',
            'horse_crm' => 'CRM koni + klientów',
            'online_booking' => 'Online booking (rezerwacje przez stronę)',
            'passes' => 'Karnety i auto-rozliczenia',
            'invoices_ksef' => 'Faktury VAT + KSeF',
            'breeding_journal' => 'Dziennik klaczy hodowlanych',
            'boarding_portal' => 'Pensjonat + portal właściciela',
            'public_api' => 'Public API + webhooks',
            'vanity_domain' => 'Własna domena (np. mojastajnia.pl)',
            'white_label' => 'White-label (logo + branding)',
            'sso' => 'SSO (Google Workspace / SAML)',
        ],
    ],

    'support' => [
        'community' => 'Społeczność',
        'email' => 'E-mail · 48h',
        'email_chat' => 'E-mail + chat · 24h',
        'priority' => 'Priorytet · 4h w dni robocze',
        'dedicated' => 'Dedykowany opiekun · SLA',
    ],

    'faq' => [
        'heading' => 'Najczęstsze pytania',
        'trial' => [
            'q' => 'Czy potrzebuję karty żeby zacząć?',
            'a' => 'Nie. Pełna funkcjonalność na 30 dni bez karty kredytowej. Po triale wybierasz plan dopiero gdy będziesz pewny — nie zamieniamy automatycznie na płatny.',
        ],
        'change_plan' => [
            'q' => 'Czy mogę zmienić plan w trakcie?',
            'a' => 'Tak — w dowolnym momencie. Upgrade działa od razu, downgrade od najbliższego okresu rozliczeniowego.',
        ],
        'cancel' => [
            'q' => 'Czy mogę zrezygnować?',
            'a' => 'Tak, w dowolnym momencie. Bez podpisywania umów, bez okresu wypowiedzenia. Zrezygnowałaś — nie płacisz za kolejny miesiąc.',
        ],
        'data_ownership' => [
            'q' => 'Czyje są moje dane?',
            'a' => 'Wyłącznie Twoje. Możesz wyeksportować pełen backup (kalendarz, klienci, konie, faktury) w formacie CSV/iCal w dowolnej chwili — także po rezygnacji.',
        ],
        'invoice' => [
            'q' => 'Czy dostanę fakturę VAT?',
            'a' => 'Tak. Fakturę VAT 23% wystawiamy automatycznie po opłaceniu — z polskim NIP-em i pełnym opisem usługi.',
        ],
        'limits_exceeded' => [
            'q' => 'Co jeśli przekroczę limit koni?',
            'a' => 'Damy znać i poprosimy o upgrade — nigdy nie zablokujemy nagle dostępu w środku turnusu. Masz 30 dni na decyzję.',
        ],
    ],

    'nav' => [
        'demo' => 'Demo',
        'login' => 'Zaloguj się',
        'signup' => 'Załóż konto',
    ],

    'footer' => [
        'signup' => 'Załóż konto za darmo',
        'demo' => 'Najpierw zobacz demo',
    ],
];
