<?php

declare(strict_types=1);

return [
    'tenant_type' => [
        'stable' => 'Stajnia',
        'transporter' => 'Firma transportowa',
    ],

    'quote_status' => [
        'draft' => 'Szkic',
        'sent' => 'Wysłana',
        'accepted' => 'Zaakceptowana',
        'rejected' => 'Odrzucona',
        'expired' => 'Wygasła',
        'withdrawn' => 'Wycofana',
    ],

    'verification_status' => [
        'pending' => 'Oczekuje na dokumenty',
        'under_review' => 'W weryfikacji',
        'verified' => 'Zweryfikowane',
        'rejected' => 'Odrzucone',
    ],

    'transport_lead_status' => [
        'open' => 'Otwarte',
        'quoted' => 'Z ofertą',
        'accepted' => 'Zaakceptowane',
        'rejected' => 'Odrzucone',
        'expired' => 'Wygasłe',
        'cancelled' => 'Anulowane',
    ],

    'transport_invoice_kind' => [
        'fv' => 'Faktura VAT',
        'fv_proforma' => 'Faktura pro forma',
        'fv_korekta' => 'Faktura korygująca',
    ],

    'transport_invoice_status' => [
        'draft' => 'Szkic',
        'issued' => 'Wystawiona',
        'paid' => 'Zapłacona',
        'overdue' => 'Przeterminowana',
        'void' => 'Anulowana',
        'cancelled' => 'Wycofana',
    ],

    'transporter_document_type' => [
        'company_registration' => 'Dane rejestrowe firmy',
        'company_registration_description' => 'Wpis do KRS lub CEIDG (PDF, JPG).',

        // Legacy — deprecated, ukryte w nowym UI ale zachowane dla wstecznej kompatybilności.
        'animal_transport_cert' => 'Świadectwo transportu zwierząt (legacy)',
        'animal_transport_cert_description' => 'Stare świadectwo z ustawy 1/2005 — zastąpione przez świadectwo zatwierdzenia środka transportu PLW.',
        'insurance_ocp' => 'Ubezpieczenie OC przewoźnika (legacy)',
        'insurance_ocp_description' => 'Zastąpione przez „OC Przewoźnika" w nowej liście PLW.',
        'insurance_ocs' => 'Ubezpieczenie OC cargo',
        'insurance_ocs_description' => 'OCS — polisa za szkody na ładunku (np. uraz konia w trakcie transportu). Opcjonalne, ale rekomendowane.',
        'vehicle_registration' => 'Dowód rejestracyjny pojazdu (legacy)',
        'vehicle_registration_description' => 'Skan DR — zastąpione przez świadectwo zatwierdzenia środka transportu PLW.',

        // PLW — Przewóz Wewnątrzwspólnotowy Zwierząt Żywych.
        'road_carrier_license' => 'Zezwolenie na wykonywanie zawodu Przewoźnika Drogowego',
        'road_carrier_license_description' => 'Wydane przez GITD lub starostę zgodnie z Rozp. WE 1071/2009 + ustawą o transporcie drogowym z 2001 r. Sprawdzamy ważność i zakres.',
        'pwl_authorization_type1' => 'Zezwolenie dla Przewoźnika Typ 1 (PLW, < 8h)',
        'pwl_authorization_type1_description' => 'Autoryzacja PIW dla transportów do 8 godzin. Wybierz Typ 1 jeśli wykonujesz wyłącznie krótkie trasy.',
        'pwl_authorization_type2' => 'Zezwolenie dla Przewoźnika Typ 2 (PLW, > 8h)',
        'pwl_authorization_type2_description' => 'Autoryzacja PIW dla transportów powyżej 8 godzin (transporty długie). Pokrywa również Typ 1.',
        'pwl_driver_handler_certificate' => 'Licencje dla kierowców i osób obsługujących (PLW)',
        'pwl_driver_handler_certificate_description' => 'Świadectwa kompetencji kierowców i osób obsługujących zwierzęta, art. 6 Rozp. WE 1/2005. Wgraj komplet dla całego zespołu.',
        'pwl_vehicle_approval_certificate' => 'Świadectwo Zatwierdzenia Środka Transportu (PLW)',
        'pwl_vehicle_approval_certificate_description' => 'Art. 18 (< 8h) lub art. 19 (> 8h) Rozp. WE 1/2005. Dokument dla każdego pojazdu używanego do przewozu koni.',
        'wash_disinfection_log' => 'Książka mycia i dezynfekcji Środka Transportu',
        'wash_disinfection_log_description' => 'Wymóg ustawy o ochronie zdrowia zwierząt z 2004 r. Wgraj aktualne wpisy z ostatnich 12 miesięcy.',
        'carrier_liability_insurance' => 'OC Przewoźnika',
        'carrier_liability_insurance_description' => 'Polisa odpowiedzialności cywilnej przewoźnika drogowego. Sprawdzamy datę ważności i sumę gwarancyjną.',

        'other' => 'Inny dokument',
        'other_description' => 'Custom — np. zaświadczenie z PIP, licencja wspólnotowa, polisa cargo nadkład.',
    ],

    'boarding_frequency' => [
        'daily' => 'Dziennie',
        'monthly' => 'Miesięcznie',
        'per_use' => 'Za użycie',
        'once' => 'Jednorazowo',
    ],

    'calendar_entry_status' => [
        'requested' => 'Zgłoszone',
        'confirmed' => 'Potwierdzone',
        'cancelled' => 'Anulowane',
        'completed' => 'Zakończone',
        'no_show' => 'Nieobecność',
    ],

    'calendar_entry_type' => [
        'lesson_individual' => 'Jazda indywidualna',
        'lesson_group' => 'Jazda grupowa',
        'training' => 'Trening',
        'care' => 'Opieka (wet/kowal)',
        'event' => 'Wydarzenie',
        'block' => 'Blokada',
    ],

    'health_record_type' => [
        'vaccination' => 'Szczepienie',
        'deworming' => 'Odrobaczanie',
        'vet_visit' => 'Wizyta weterynaryjna',
        'farrier' => 'Kowal',
        'dentist' => 'Dentysta',
        'check_up' => 'Badanie kontrolne',
        'medication' => 'Leki',
        'other' => 'Inne',
    ],

    'horse_document_kind' => [
        'passport' => 'Paszport konia',
        'contract' => 'Umowa pensjonatu',
        'insurance' => 'Polisa / ubezpieczenie',
        'vaccine_book' => 'Książka szczepień',
        'ownership_proof' => 'Dowód własności',
        'competition_licence' => 'Licencja zawodnicza',
        'vet_certificate' => 'Zaświadczenie weterynaryjne',
        'other' => 'Inny',
    ],

    'invoice_kind' => [
        'fv' => 'Faktura VAT',
        'fv_proforma' => 'Faktura Proforma',
        'fv_korekta' => 'Faktura Korygująca',
    ],

    'invoice_status' => [
        'draft' => 'Wersja robocza',
        'issued' => 'Wystawiona',
        'paid' => 'Opłacona',
        'overdue' => 'Po terminie',
        'void' => 'Anulowana',
        'cancelled' => 'Skorygowana',
    ],

    'pass_status' => [
        'active' => 'Aktywny',
        'exhausted' => 'Wykorzystany',
        'expired' => 'Wygasły',
        'cancelled' => 'Anulowany',
    ],

    'payment_provider' => [
        'none' => 'Brak (płatność offline)',
        'stub' => 'Test (developer)',
        'p24' => 'Przelewy24',
        'payu' => 'PayU',
        'stripe' => 'Stripe',
        'mollie' => 'Mollie',
    ],

    'payment_status' => [
        'pending' => 'Oczekująca',
        'processing' => 'Przetwarzanie',
        'succeeded' => 'Opłacona',
        'failed' => 'Nieudana',
        'refunded' => 'Zwrócona',
    ],

    'recurrence_pattern' => [
        'daily' => 'Codziennie',
        'weekly' => 'Co tydzień',
        'monthly' => 'Co miesiąc',
    ],

    'stable_activity_type' => [
        'feeding' => 'Karmienie',
        'grooming' => 'Czyszczenie / pielęgnacja',
        'turnout' => 'Wypuszczenie na padok',
        'exercise' => 'Praca z koniem',
        'box_cleaning' => 'Sprzątanie boksu',
        'transport_event' => 'Wyjazd / event',
        'other' => 'Inne',
    ],

    'feeding_meal' => [
        'breakfast' => 'Rano',
        'midday' => 'Południe',
        'evening' => 'Wieczór',
        'night' => 'Noc',
    ],
];
