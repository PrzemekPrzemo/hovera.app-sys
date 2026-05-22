<?php

declare(strict_types=1);

return [
    'title' => 'Witaj w Hovera Transport',
    'navigation_label' => 'Pierwsze kroki',
    'welcome' => [
        'heading' => 'Krok 1 z 3 — Twoja firma transportowa w Hoverze',
        'body' => 'Przeprowadzimy Cię przez 3 najważniejsze ustawienia: dokumenty PWL, strefy działania i KSeF. Wszystko zajmie 10–15 minut.',
    ],
    'steps' => [
        'documents' => [
            'title' => 'Dokumenty PWL',
            'description' => 'Weryfikacja przez zespół Hovery',
            'body' => 'Wgraj 6 wymaganych dokumentów: zezwolenie GITD, zezwolenie PWL (Typ 1/2), licencje kierowców, świadectwa zatwierdzenia pojazdów, książka mycia, OC. Bez weryfikacji nie wystawisz ofert. Szczegóły w „Centrum pomocy → Firma transportowa → Dokumenty PWL".',
            'cta_hint' => 'Otwórz w nowej karcie:',
            'cta' => 'Wgraj dokumenty',
        ],
        'coverage' => [
            'title' => 'Strefy działania + cennik',
            'description' => 'Gdzie wozisz i za ile',
            'body' => 'Zaznacz województwa w których działasz (filtr katalogu pokaże Cię klientom z regionu) i ustaw cennik bazowy (zł/km + minimum). Kalkulator wyceny używa tych wartości jako default — możesz potem nadpisać per ofertę.',
            'cta_areas' => 'Strefy działania',
            'cta_pricing' => 'Cennik bazowy',
        ],
        'ksef' => [
            'title' => 'KSeF',
            'description' => 'Certyfikat + środowisko',
            'body' => 'Od 2026-02 KSeF obowiązkowy. Wgraj certyfikat (PFX/PEM), wybierz tryb test (do nauki) lub prod (po lutym). Bez KSeF wystawisz FV lokalnie, ale wysłanie do MF będzie ręczne.',
            'cta_hint' => 'Otwórz w nowej karcie:',
            'cta' => 'Ustawienia KSeF (tab w Transport Settings)',
        ],
    ],
    'action' => [
        'finish' => 'Zakończ wizard',
        'skip' => 'Pomiń wizard',
    ],
    'notify' => [
        'completed_title' => 'Wizard ukończony',
        'completed_body' => 'Gdy zespół Hovery zweryfikuje dokumenty, Twoja firma pojawi się w publicznym katalogu /przewoznicy.',
        'skipped_title' => 'Wizard pominięty',
        'skipped_body' => 'Dokumenty PWL musisz wgrać przed pierwszą ofertą — wracaj do Ustawień transportu, gdy będziesz gotowy.',
    ],
];
