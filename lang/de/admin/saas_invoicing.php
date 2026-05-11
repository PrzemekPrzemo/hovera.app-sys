<?php

declare(strict_types=1);

return [
    'navigation' => 'SaaS-Rechnungsnummerierung',
    'title' => 'Nummerierung und Vorlagen der hovera-Rechnungen',

    'section' => [
        'numbering' => 'Nummerierung',
        'numbering_help' => 'Nummern-Vorlage zur Generierung fortlaufender SaaS-Rechnungen (hovera → Reitstall). Tokens: {YYYY} Jahr 4-stellig, {YY} Jahr 2-stellig, {MM} Monat 2-stellig, {NNNN} Sequenz zero-padded 4-stellig, {NN} Sequenz 2-stellig, {SEQ} Sequenz ohne Padding.',
        'defaults' => 'Rechnungs-Standardwerte',
        'text' => 'Inhalt fester Felder',
        'text_help' => 'Text, der in jede ausgestellte Rechnung eingefügt wird — Zahlungsbedingungen, Fußzeile mit Kontonummer, Kontaktinformationen.',
    ],

    'field' => [
        'number_template' => 'Nummerierungs-Vorlage',
        'number_template_help' => 'Beispiel: HVR/{YYYY}/{MM}/{NNNN} → HVR/2026/05/0042',
        'reset_cycle' => 'Reset-Zyklus der Sequenz',
        'next_sequence' => 'Nächste Nummer (Override)',
        'next_sequence_placeholder' => 'leer lassen, um fortzufahren',
        'next_sequence_help' => 'Wenn Sie z. B. 100 eingeben, verwendet die nächste ausgestellte Rechnung die Sequenz 100 (dann 101, 102…). Nützlich nach einem Import aus einem anderen System.',
        'currency' => 'Währung',
        'vat_rate' => 'MwSt.-Satz',
        'due_days' => 'Zahlungsfrist',
        'due_days_suffix' => 'Tage',
        'payment_terms' => 'Zahlungsbedingungen',
        'payment_terms_placeholder' => 'z. B. „Zahlbar innerhalb von 14 Tagen ab Ausstellungsdatum. Konto: ..."',
        'footer_note' => 'Rechnungs-Fußzeile',
        'footer_note_help' => 'Wird unten auf jeder PDF-Rechnung gedruckt + in das KSeF-XML als optionales Feld eingetragen.',
        'footer_note_placeholder' => 'z. B. „Vielen Dank für die Zusammenarbeit! Fragen? support@hovera.app"',
    ],

    'cycle' => [
        'monthly' => 'Monatlich (Reset am 1. des Monats)',
        'yearly' => 'Jährlich (Reset am 1. Januar)',
        'never' => 'Nie (fortlaufende Sequenz)',
    ],

    'action' => [
        'save_button' => 'Konfiguration speichern',
        'saved' => 'Nummerierungs-Konfiguration gespeichert.',
    ],
];
