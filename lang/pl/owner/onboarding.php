<?php

declare(strict_types=1);

return [
    'title' => 'Witaj, właścicielu konia',
    'navigation_label' => 'Pierwsze kroki',
    'welcome' => [
        'heading' => 'Krok 1 z 3 — Twoje konto Hovera',
        'body' => 'Konto bezpłatne na zawsze — opłacają je stajnie i przewoźnicy. Pokażemy Ci 3 najważniejsze rzeczy: jak dodać konia, jak znaleźć ulubionych przewoźników i jak zamówić pierwszy transport.',
    ],
    'steps' => [
        'horse' => [
            'title' => 'Mój pierwszy koń',
            'description' => 'Paszport, rasa, mikrochip',
            'body' => 'Dodaj swojego konia — przynajmniej imię, rasę, datę urodzenia i numer paszportu. Zdjęcie + mikrochip są opcjonalne, ale przydają się przy transportach (przewoźnicy widzą konia przy odbiorze).',
            'cta_hint' => 'Otwórz w nowej karcie:',
            'cta' => 'Dodaj konia',
        ],
        'favorites' => [
            'title' => 'Ulubieni przewoźnicy',
            'description' => 'Lista zaufanych firm',
            'body' => 'Po wykonaniu kilku transportów zauważysz że są przewoźnicy z którymi pracuje Ci się lepiej. Zapisz ich w „Ulubieni" — przy nowym zapytaniu zaznaczysz „TYLKO do moich ulubionych" i broadcast nie zaleje konkurencji.',
            'optional' => '(opcjonalne — możesz pominąć i wrócić później)',
            'cta' => 'Lista ulubionych przewoźników',
        ],
        'first_order' => [
            'title' => 'Pierwsze zamówienie transportu',
            'description' => 'Wycena automatyczna + 24h na oferty',
            'body' => 'Wpisz Skąd → Dokąd → datę → konia → klikasz „Wyślij". Zapytanie poleci do wszystkich zweryfikowanych przewoźników w okolicy. Oferty pojawią się w 24h, porównujesz ceny, klikasz „Akceptuj" — jedna wygrywa, reszta automatycznie wycofana.',
            'cta_hint' => 'Otwórz w nowej karcie:',
            'cta' => 'Zamów transport',
        ],
    ],
    'action' => [
        'finish' => 'Zakończ wizard',
        'skip' => 'Pomiń wizard',
    ],
    'notify' => [
        'completed_title' => 'Wszystko gotowe',
        'completed_body' => 'Gdy będziesz potrzebować pomocy — w menu jest „Centrum pomocy → Właściciel konia" z pełną instrukcją.',
        'skipped_title' => 'Wizard pominięty',
        'skipped_body' => 'W każdej chwili możesz wrócić do „Moich koni" lub „Zamów transport" z menu.',
    ],
];
