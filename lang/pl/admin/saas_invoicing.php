<?php

declare(strict_types=1);

return [
    'navigation' => 'Numeracja faktur SaaS',
    'title' => 'Numeracja i szablony faktur hovera',

    'section' => [
        'numbering' => 'Numeracja',
        'numbering_help' => 'Szablon numeru używany do generowania kolejnych faktur SaaS (hovera → stajnia). Tokeny: {YYYY} rok 4-cyfr, {YY} rok 2-cyfr, {MM} miesiąc 2-cyfr, {NNNN} sekwencja zero-padded 4-cyfr, {NN} sekwencja 2-cyfr, {SEQ} sekwencja bez paddingu.',
        'defaults' => 'Domyślne wartości faktury',
        'text' => 'Treść stałych pól',
        'text_help' => 'Tekst wstawiany do każdej wystawianej FV — warunki płatności, stopka z numerem konta, info kontaktowe.',
    ],

    'field' => [
        'number_template' => 'Szablon numeracji',
        'number_template_help' => 'Przykład: HVR/{YYYY}/{MM}/{NNNN} → HVR/2026/05/0042',
        'reset_cycle' => 'Cykl resetowania sekwencji',
        'next_sequence' => 'Następny numer (override)',
        'next_sequence_placeholder' => 'pozostaw puste aby kontynuować',
        'next_sequence_help' => 'Jeśli wpiszesz np. 100 — kolejna wystawiona FV użyje sekwencji 100 (i potem 101, 102…). Przydatne po imporcie z innego systemu.',
        'currency' => 'Waluta',
        'vat_rate' => 'Stawka VAT',
        'due_days' => 'Termin płatności',
        'due_days_suffix' => 'dni',
        'payment_terms' => 'Warunki płatności',
        'payment_terms_placeholder' => 'np. "Płatne w ciągu 14 dni od daty wystawienia. Konto: ..."',
        'footer_note' => 'Stopka faktury',
        'footer_note_help' => 'Drukowana na dole każdej FV PDF + wpisana do XML KSeF jako pole opcjonalne.',
        'footer_note_placeholder' => 'np. "Dziękujemy za współpracę! Pytania? office@hovera.app"',
    ],

    'cycle' => [
        'monthly' => 'Miesięcznie (reset 1. dnia miesiąca)',
        'yearly' => 'Rocznie (reset 1. stycznia)',
        'never' => 'Nigdy (ciągła sekwencja)',
    ],

    'action' => [
        'save_button' => 'Zapisz konfigurację',
        'saved' => 'Konfiguracja numeracji zapisana.',
    ],
];
