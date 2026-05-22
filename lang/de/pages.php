<?php

declare(strict_types=1);

return [
    'profile' => [
        'navigation' => 'Profil',
        'title' => 'Ihr Profil',
    ],

    'calendar' => [
        'navigation' => 'Tagesplan',
        'livejumping' => [
            'heading' => 'Wettbewerbe (LiveJumping) — die nächsten 7 Tage',
            'more' => 'mehr',
        ],
    ],

    'tenant_settings' => [
        'navigation' => 'Reitstall-Einstellungen',
        'title' => 'Reitstall-Einstellungen',
    ],

    'invoicing_settings' => [
        'navigation' => 'Rechnungen und Abrechnung',
        'title' => 'Rechnungen und Abrechnung',
    ],

    'payment_settings' => [
        'navigation' => 'Online-Zahlungen',
        'title' => 'Online-Zahlungen',
    ],

    'ksef_settings' => [
        'navigation' => 'KSeF (E-Rechnungen)',
        'title' => 'KSeF — nationales E-Rechnungssystem',
    ],

    'company_lookup' => [
        'navigation' => 'GUS / KRS',
        'title' => 'Unternehmensprüfung — GUS / KRS',
    ],

    'my_tasks' => [
        'navigation' => 'Meine Aufgaben',
        'title' => 'Meine Aufgaben',
        'signed_in_as' => 'Angemeldet als Spezialist',
        'sections' => [
            'overdue' => 'Überfällig',
            'upcoming' => 'Anstehende Behandlungen (30 Tage)',
            'recent' => 'Kürzlich durchgeführt (30 Tage)',
        ],
        'empty' => [
            'overdue' => 'Keine überfälligen Aufgaben — gut gemacht!',
            'upcoming' => 'Keine geplanten Behandlungen in den nächsten 30 Tagen.',
            'recent' => 'Keine Einträge aus den letzten 30 Tagen.',
        ],
        'overdue_by_days' => '{1} überfällig seit 1 Tag|[2,*] überfällig seit :days Tagen',
        'in_days' => '{0} heute|{1} morgen|[2,*] in :days Tagen',
    ],

    'help' => [
        'navigation' => 'Hilfe',
        'title' => 'Hilfezentrum',
        'tab' => [
            'manual' => 'Bedienungsanleitung',
            'legal' => 'Rechtsdokumente',
        ],
        'persona' => [
            'owner' => 'Inhaber / Administrator',
            'owner_desc' => 'Vollständiges Panel, Finanzen, Team, Stalleinstellungen.',
            'employee' => 'Mitarbeiter / Trainer',
            'employee_desc' => 'Kalender, Kunden, Pferde — der tägliche Betrieb.',
            'specialist' => 'Tierarzt / Spezialist',
            'specialist_desc' => 'Gesundheitsakten, Besuche, Pferdebehandlung.',
            'client' => 'Stallkunde',
            'client_desc' => 'Portal: Buchungen, Karten, mein Pferd.',
            'transporter' => 'Transportunternehmen',
            'transporter_desc' => 'Fahrzeuge, Fahrer, Leads, Angebote, PLW-Dokumente.',
            'horse_owner' => 'Pferdebesitzer',
            'horse_owner_desc' => 'KOSTENLOSES Konto — meine Pferde, Transportbestellungen, Rechnungshistorie.',
        ],
        'legal' => [
            'open_in_new_tab' => 'Öffentliche Version öffnen',
        ],
        'topbar' => [
            'help' => 'Hilfezentrum',
            'report_bug' => 'Fehler / Vorschlag melden',
        ],
        'public_lead' => 'Bedienungsanleitungen pro Rolle und vollständige Rechtsdokumentation von hovera. Ohne Anmeldung verfügbar, in 5 Sprachen.',
        'public_cta' => 'Möchten Sie hovera in Ihrem Stall ausprobieren? 30 Tage kostenlos, ohne Karte.',
        'public_meta_desc' => 'Bedienungsanleitungen, AGB, Datenschutzerklärung und AVV für hovera — Verwaltungssystem für Reitställe.',
        'bug_report' => [
            'title' => 'Fehler oder Vorschlag melden',
            'lead' => 'Ihre Meldung geht direkt an das hovera-Team in Todoist — zusammen mit der URL der Seite, auf der Sie sich befinden.',
            'kind_label' => 'Typ',
            'kind_bug' => 'Fehler',
            'kind_idea' => 'Vorschlag / Änderung',
            'subject_label' => 'Kurzer Titel',
            'subject_placeholder' => 'z. B. Karte lässt sich nicht löschen',
            'description_label' => 'Beschreibung',
            'description_placeholder' => 'Was ist passiert? Was hätte passieren sollen? Schritte zur Reproduktion.',
            'screenshot_label' => 'Screenshot (PNG/JPG, optional)',
            'submit' => 'Meldung senden',
            'cancel' => 'Abbrechen',
            'success' => 'Danke — Ihre Meldung wurde gesendet.',
            'error' => 'Senden fehlgeschlagen. Bitte erneut versuchen oder an office@hovera.app schreiben.',
        ],
    ],

    'reports' => [
        'month_picker' => 'Monat',
        'apply' => 'Anzeigen',
        'empty' => 'Keine Daten für den gewählten Monat.',
        'col_item' => 'Position',
        'col_total' => 'Nettowert',

        'revenue' => [
            'navigation' => 'Umsatz',
            'title' => 'Monatsbericht — Umsatz',
            'total_heading' => 'Summe netto · :month',
            'invoice_count' => 'Rechnungen im Zeitraum: :count',
            'top_items' => 'Top 10 Positionen',
            'bucket' => [
                'boarding' => 'Pension',
                'lessons' => 'Reitstunden',
                'passes' => 'Reitkarten',
                'other' => 'Sonstiges',
            ],
        ],

        'aging' => [
            'navigation' => 'Forderungsalterung',
            'title' => 'Forderungsalterung',
            'total_heading' => 'Gesamt überfällig',
            'list_heading' => 'Liste überfälliger Rechnungen',
            'empty' => 'Keine überfälligen Rechnungen — alles bezahlt.',
            'col_invoice' => 'Rechnungsnr.',
            'col_client' => 'Kunde',
            'col_due_at' => 'Fällig am',
            'col_days_overdue' => 'Tage überfällig',
            'col_amount' => 'Bruttobetrag',
            'days' => 'Tage',
            'bucket' => [
                '0_30' => '1–30 Tage',
                '31_60' => '31–60 Tage',
                '61_90' => '61–90 Tage',
                '90_plus' => '> 90 Tage',
            ],
        ],

        'horse_utilization' => [
            'navigation' => 'Pferdeauslastung',
            'title' => 'Pferdeauslastung',
            'heading' => 'Reitstunden pro Pferd · :month',
            'subtitle' => 'Anzahl bestätigter / abgeschlossener Buchungen im gewählten Monat. Über 25 Reitstunden = Risiko der Überlastung.',
            'col_horse' => 'Pferd',
            'col_lessons' => 'Reitstunden',
            'col_hours' => 'Stunden',
        ],

        'instructor_utilization' => [
            'navigation' => 'Reitlehrer-Auslastung',
            'title' => 'Reitlehrer-Auslastung',
            'heading' => 'Stunden und Anwesenheit · :month',
            'col_instructor' => 'Reitlehrer',
            'col_lessons' => 'Reitstunden',
            'col_hours' => 'Stunden',
            'col_cancelled' => 'Storniert',
            'col_no_show' => 'No-Show',
            'col_attendance' => 'Anwesenheit',
        ],
    ],

    'bulk_invoicing' => [
        'navigation' => 'Sammelrechnungen Monat',
        'title' => 'Bulk Invoicing — Sammelrechnungen Pension',
        'month_picker' => 'Abzurechnender Monat',
        'refresh' => 'Vorschau aktualisieren',
        'helper' => 'Erzeugt eine Rechnungsentwurf-Version (Draft) für jeden Kunden auf Basis der aktiven Pensionsleistungen seiner Pferde. Reitkarten werden beim Verkauf abgerechnet und gehen nicht in den Bulk-Lauf ein. Jeden Draft bestätigen Sie einzeln unter Rechnungen → Ausstellen.',
        'preview_heading' => 'Vorschau · :month · :count Kunden',
        'empty' => 'Keine Abrechnungen für den gewählten Monat. Prüfen Sie, ob den Pferden Pensionsleistungen zugewiesen sind, die im Zeitraum aktiv sind.',
        'items_suffix' => 'Positionen',
        'col_item' => 'Position',
        'col_qty' => 'Menge',
        'col_unit_price' => 'Preis/Einh.',
        'col_net' => 'Netto',
        'col_gross' => 'Brutto',
        'totals' => 'Summe (markierte oder alle):',
        'net_short' => 'netto',
        'gross_short' => 'brutto',
        'actions' => [
            'generate' => 'Drafts erzeugen',
        ],
        'confirm' => [
            'heading' => 'Rechnungs-Drafts erzeugen?',
            'description' => 'Wir erstellen Entwurfsrechnungen für :month für die markierten Kunden (oder alle aus der Vorschau). Jede bestätigen Sie einzeln unter Rechnungen.',
            'submit' => 'Ja, erzeugen',
        ],
        'flash' => [
            'success' => ':count Drafts erzeugt. Wechseln Sie zum Tab Rechnungen, um sie auszustellen.',
        ],
    ],
];
