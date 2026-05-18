<?php

declare(strict_types=1);

return [
    'tenant_type' => [
        'stable' => 'Stajnia',
        'transporter' => 'Firma transportowa',
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
