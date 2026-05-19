<?php

declare(strict_types=1);

return [
    // SEO meta. :count = liczba zweryfikowanych firm w wyniku (po filtrach).
    'meta_title' => 'Przewoźnicy koni w Polsce — :count firm zweryfikowanych | hovera',
    'meta_description' => 'Profesjonalne firmy transportu koni z całej Polski. Marketplace zweryfikowany przez Hovera.',

    'hero_title' => 'Profesjonalni przewoźnicy koni',
    'hero_subtitle' => ':count firm zweryfikowanych przez Hovera. Wybierz region lub wyszukaj po nazwie.',

    'filter_voivodeship_label' => 'Województwo',
    'filter_voivodeship_all' => 'Wszystkie województwa',
    'filter_search_placeholder' => 'Nazwa firmy...',
    'filter_search_label' => 'Szukaj',
    'filter_apply' => 'Filtruj',

    'sort_label' => 'Sortowanie',
    'sort_recent' => 'Najnowsze',
    'sort_rating_desc' => 'Ocena malejąco',
    'sort_name_asc' => 'Nazwa A-Z',

    'clear_filters' => 'Wyczyść filtry',

    'empty_state_title' => 'Brak transporterów spełniających kryteria.',
    'empty_state_subtitle' => 'Spróbuj rozszerzyć filtry albo poszukaj sąsiednich województw.',
    'empty_state_action' => 'Pokaż wszystkich',
    'empty_state_transporter_hint' => 'Twoja firma transportowa? Dołącz do bazy:',
    'empty_state_transporter_cta' => 'Zarejestruj firmę',
    'hero_cta_join' => 'Jesteś przewoźnikiem? Dołącz do bazy',
    'card_voiv_more_tooltip' => 'Pokrywa więcej województw — szczegóły na profilu',

    'card_view_profile' => 'Zobacz profil',
    'featured_badge' => 'Polecany',
    // Spójne z §12 docs/TRANSPORT.md — pokazujemy że za jakością odpowiada
    // transporter, Hovera weryfikuje dokumenty, ale nie wykonuje transportów.
    'card_disclaimer_verified' => 'Zweryfikowany przez Hovera. Hovera = pośrednik marketplace.',
    'card_disclaimer_link_label' => 'regulamin',

    'card_reviews_count_zero' => 'Brak opinii',
    // trans_choice — Polish 3-form plural: 1 / 2-4 / 5+.
    'card_reviews_avg' => '{1} :avg z :count opinii|{2,3,4} :avg z :count opinii|[5,*] :avg z :count opinii',

    'cta_inquiry_section_title' => 'Nie chcesz przeglądać?',
    'cta_inquiry_section_text' => 'Wyślij jedno zapytanie do wszystkich przewoźników z Twojego regionu — odpowiedzą najszybciej.',
    'cta_inquiry_button' => 'Wyślij zapytanie do wszystkich',

    'footer_cta_join_marketplace' => 'Jesteś przewoźnikiem? Dołącz do marketplace',

    // Link z /t/{slug} z powrotem do katalogu — pomaga SEO crawlerom + UX.
    'back_to_directory' => '← Wszystkie firmy',

    // Link na /transport/zapytanie — subtelnie zachęca do przeglądania ręcznie.
    'link_browse_directory_from_inquiry' => 'Lub przeglądaj listę zweryfikowanych przewoźników →',
];
