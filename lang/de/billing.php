<?php

declare(strict_types=1);

return [
    'navigation' => [
        'label' => 'hovera-Abonnement',
    ],
    'page' => [
        'title' => 'hovera-Abonnement',
        'subtitle' => 'Wählen Sie einen Tarif für den Reitstall :stable. Wiederkehrende Kartenzahlung — jederzeit kündbar.',
        'redirecting' => 'Weiterleitung zur Abrechnungsseite…',
        'click_here' => 'Falls der Browser nicht automatisch weiterleitet, klicken Sie hier.',
    ],
    'status' => [
        'active' => 'Abonnement aktiv',
        'trial_expired' => 'Testphase abgelaufen — Tarif wählen',
        'trial_days_left' => '{1} :days Tag Testphase|[2,*] :days Tage Testphase',
    ],
    'period' => [
        'label' => 'Abrechnungszeitraum',
        'monthly' => 'Monatlich',
        'yearly' => 'Jährlich (-10 %)',
        'month_short' => 'Mon.',
        'year_short' => 'Jahr',
        'one_time' => 'Einmalig',
    ],
    'actions' => [
        'choose' => 'Tarif wählen',
        'current' => 'Ihr aktueller Tarif',
        'manage' => 'Abonnement verwalten',
        'back_to_app' => 'Zurück zur App',
    ],
    'manage' => [
        'title' => 'Abonnement verwalten',
        'description' => 'Karte ändern, Rechnungen herunterladen oder Abonnement im Stripe-Portal kündigen.',
    ],
    'return' => [
        'title' => 'Abonnement',
        'success_title' => 'Abonnement aktiv',
        'success_body' => 'Vielen Dank! Ihr hovera-Abonnement wurde aktiviert — die Rechnung erhalten Sie per E-Mail.',
        'go_to_app' => 'Zur App',
        'pending_title' => 'Zahlung wird verarbeitet',
        'pending_body' => 'Stripe bestätigt die Zahlung — dies kann einige Sekunden dauern. Aktualisieren Sie die Seite in Kürze.',
        'refresh' => 'Aktualisieren',
    ],
    'errors' => [
        'unknown_plan' => 'Der gewählte Tarif existiert nicht oder ist inaktiv.',
        'checkout_failed' => 'Die Zahlungssitzung konnte nicht erstellt werden. Bitte erneut versuchen oder uns kontaktieren.',
        'portal_failed' => 'Das Abrechnungsportal konnte nicht geöffnet werden. Bitte kontaktieren Sie uns.',
    ],
    'footer' => [
        'disclaimer' => 'Zahlungen werden über Stripe abgewickelt. Ihre Kartendaten werden nicht auf hovera-Servern gespeichert. Mehrwertsteuerrechnungen werden nach jeder erfolgreichen Abbuchung automatisch erstellt.',
    ],
    'suggested_badge' => 'Empfohlen',
    'trial_banner' => [
        'expires_today' => 'Ihre Testphase endet heute.',
        'expires_tomorrow' => 'Die Testphase endet morgen.',
        'days_left' => '{1} :days Tag Testphase verbleibend.|[2,*] :days Tage Testphase verbleibend.',
        'pro_pitch' => 'Sie verfügen über alle Pro-Funktionen, in der Testphase ist die Anzahl jedoch auf :horses Pferde und :clients Kunden begrenzt. Mit dem Pro-Tarif entfällt das Limit.',
        'cta_pro' => 'Pro wählen',
    ],
    'limits' => [
        'title' => 'Tariflimit erreicht',
        'horses_exceeded' => 'Testphase: Limit :limit Pferde — wählen Sie einen Tarif, um weitere hinzuzufügen.',
        'clients_exceeded' => 'Testphase: Limit :limit Kunden — wählen Sie einen Tarif, um weitere hinzuzufügen.',
        'vehicles_exceeded' => 'Limit von :limit Fahrzeugen im aktuellen Tarif — upgraden, um weitere hinzuzufügen.',
        'drivers_exceeded' => 'Limit von :limit Fahrern im aktuellen Tarif — upgraden, um weitere hinzuzufügen.',
    ],
    'onboarding_fee' => [
        'label' => 'Einrichtungsgebühr — Tarif :plan',
        'description' => 'Einmalige Aktivierungsgebühr zu Beginn des Abonnements.',
    ],
    'onboarding_fee_label' => 'einmalig (Einrichtungsgebühr)',
    'vat_notice' => 'Preise netto. Jedem Betrag wird 23 % MwSt. hinzugerechnet.',
    'vat_notice_short' => '+ 23 % MwSt.',
    'email' => [
        'invoice_paid' => [
            'subject' => 'Rechnung :number — bezahlt, vielen Dank!',
            'heading' => 'Rechnung :number bezahlt',
            'intro' => 'Vielen Dank! Wir haben die Zahlung für Ihr hovera-Abonnement für den Reitstall :stable erhalten.',
            'field_number' => 'Rechnungsnummer',
            'field_plan' => 'Tarif',
            'field_period' => 'Zeitraum',
            'field_total' => 'Bruttobetrag',
            'field_paid_at' => 'Bezahlt am',
            'pdf_pending' => 'Die PDF-Rechnung erscheint in Kürze in Ihrem Abrechnungsbereich. Die Rechnung wird zudem an KSeF übermittelt (falls konfiguriert).',
            'cta_billing' => 'Abrechnungsbereich öffnen',
            'thanks' => 'Schön, dass Sie bei uns sind!',
            'signoff' => 'Mit freundlichen Grüßen,',
        ],
    ],
];
