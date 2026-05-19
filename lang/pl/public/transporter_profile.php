<?php

declare(strict_types=1);

return [
    'title_suffix' => 'transport koni',
    'meta_description_fallback' => ':name — profesjonalny transport koni. Bezpłatna wycena na hovera.app.',
    'default_tagline' => 'Profesjonalny transport koni',

    'cta_inquiry' => 'Zapytaj o transport',

    'section_about' => 'O firmie',
    'section_fleet' => 'Nasza flota',
    'section_coverage' => 'Obszar działania',
    'section_contact' => 'Kontakt',

    'vehicle_capacity' => '{1} :count koń|{2,3,4} :count konie|[5,*] :count koni',
    'vehicle_year' => 'rocznik :year',

    'feature_air_suspension' => 'Zawieszenie pneumatyczne',
    'feature_camera' => 'Kamery wewnątrz',
    'feature_climate' => 'Klimatyzacja',

    'coverage_hint' => 'Województwa wyróżnione obsługujemy na stałe. Sąsiednie — na zapytanie.',

    'contact_email' => 'E-mail',
    'contact_phone' => 'Telefon',
    'contact_address' => 'Adres',
    'contact_website' => 'WWW',

    'footer_cta_text' => 'Potrzebujesz przewieźć konia? Wyślij zapytanie — odpowiemy szybko.',
    'powered_by' => 'powered by hovera.app',

    // Pod stopką profilu — sygnał że za profilem stoi transporter, Hovera = marketplace.
    // Klikalny link do regulaminu marketplace; :transporter_name = nazwa firmy (escaped przed wstawieniem).
    'disclaimer_intermediary' => 'Profil obsługiwany przez <strong>:transporter_name</strong>. Hovera = pośrednik marketplace (<a href="/regulamin-marketplace" target="_blank">regulamin</a>) — nie wykonuje transportów, nie jest stroną umowy przewozu.',

    // Badge „Zweryfikowany przez Hovera" — widoczny tylko gdy Tenant::isVerifiedTransporter() === true.
    // Hover/tooltip rozwija pełną listę zweryfikowanych dokumentów + link do §12 regulaminu marketplace.
    'verified_badge_label' => 'Zweryfikowany przez Hovera',
    'verified_badge_tooltip' => 'Hovera zweryfikowała aktualność: zezwolenia na zawód przewoźnika drogowego, PLW T1/T2, świadectw kierowców, świadectwa pojazdu, książki mycia i OC. Hovera dokonała weryfikacji dokumentów — NIE odpowiada za faktyczną realizację transportu (patrz regulamin marketplace).',
    'verified_badge_link_label' => 'regulamin marketplace',
];
