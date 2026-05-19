<?php

declare(strict_types=1);

return [
    'title' => 'Als Spediteur beitreten',
    'heading' => 'Werden Sie hovera-Transportunternehmen',
    'subtitle' => 'Marketplace für Pferdetransport — füllen Sie Ihr Profil in 10 Minuten aus, laden Sie Dokumente hoch '
        .'und wir verifizieren das Konto in 2-3 Werktagen.',
    'no_commission_banner' => 'hovera.app erhebt keine Provision von Spediteuren oder Kunden — nur ein monatliches Abo.',
    'promo' => [
        'heading' => '🎁 Aktion bis Ende Juli 2026',
        'body' => 'Volle Funktionalität. Keine Karte. Keine automatische Umwandlung in zahlungspflichtig — '
            .'wählen Sie einen Tarif erst, wenn Sie bleiben möchten.',
        'body_yearly' => 'Registrierung bis Ende Juli 2026 gewährt 30 Tage gratis, '
            .'und der Jahrespreis entspricht 10 × Monatspreis (2 Monate gratis). Zeitlich begrenztes Angebot.',
    ],
    'perks' => [
        'title' => 'Was Sie erhalten',
        'item_1' => 'Öffentliches Profil unter app.hovera.app/t/{slug} (Google-indiziert)',
        'item_2' => 'Direct Leads + Broadcasting in ganz Polen (PLW)',
        'item_3' => 'Angebotsrechner + automatisches PDF + Online-Zahlung',
        'item_4' => 'Erster Monat gratis ab Verifizierungsdatum',
    ],
    'section' => [
        'company' => 'Firmendaten',
        'owner' => 'Kontakt — Inhaber / autorisierte Person',
        'documents' => 'Erforderliche Dokumente',
        'terms' => 'AGB und Zustimmungen',
    ],
    'field' => [
        'name' => 'Vollständiger Firmenname',
        'name_hint' => 'Wie in Steuernummer / Handelsregister.',
        'slug' => 'Marketplace-URL (Slug)',
        'slug_hint' => 'Nur Kleinbuchstaben, Ziffern, Bindestriche. Unveränderlich nach Registrierung.',
        'tax_id' => 'Steuernummer (NIP)',
        'tax_id_hint' => 'Nur Ziffern — 10 Stellen.',
        'regon' => 'REGON',
        'regon_hint' => '9 oder 14 Ziffern.',
        'address' => 'Firmenadresse',
        'owner_name' => 'Name',
        'owner_email' => 'Kontakt-E-Mail',
        'owner_email_hint' => 'Wir senden den Magic-Link nach Verifizierung.',
        'owner_phone' => 'Kontakttelefon',
    ],
    'documents_disclaimer' => 'Wir benötigen 6 Dokumente gemäß PLW-Gesetz und hovera-Regulierungen. '
        .'Formate: PDF, JPG, PNG. Max 5 MB pro Datei.',
    'pwl_authorization' => [
        'label' => 'PLW-Transportgenehmigung',
        'description' => 'Genehmigung für den Transport lebender Tiere nach EG-Verordnung 1/2005. '
            .'Wählen Sie den Typ: T1 (kurze Fahrten, bis 8 Std.) oder T2 (lange Fahrten, über 8 Std.) — '
            .'und laden Sie den entsprechenden Scan hoch.',
        'type_t1' => 'Typ 1 (Transport bis 8 Stunden)',
        'type_t2' => 'Typ 2 (Transport über 8 Stunden)',
    ],
    'documents' => [
        'file_hint' => 'PDF, JPG oder PNG. Max 5 MB.',
        'anonymized_heading' => 'Dokumente nur zur Verifizierung',
        'anonymized_body' => 'Nach erfolgreicher Verifizierung durch das Hovera-Team zeigen wir Kunden Dokumente NUR '
            .'in anonymisierter Form (ohne Seriennummern, Ablaufdaten, persönliche Daten). '
            .'Öffentlich sichtbar ist NUR: „✓ Vom Hovera-Team verifiziert".',
    ],
    'terms' => [
        'marketplace_position' => 'Hovera ist eine Marketplace-Plattform für Transportunternehmen. '
            .'Wir sind kein Spediteur und nicht Vertragspartei. Der Transportvertrag wird direkt zwischen '
            .'Ihnen und dem Kunden geschlossen, die Zahlung geht auf Ihr Konto.',
        'accept_html' => 'Ich akzeptiere :regulamin, :marketplace und :privacy. Ich erkläre, '
            .'dass die hochgeladenen Dokumente aktuell sind.',
        'regulamin' => 'hovera AGB',
        'marketplace' => 'Marketplace-Regulierungen',
        'privacy' => 'Datenschutzerklärung',
    ],
    'submit' => 'Registrierungsantrag senden',
    'errors' => [
        'heading' => 'Bitte überprüfen Sie das Formular:',
        'slug_format' => 'Slug darf nur Kleinbuchstaben, Ziffern und Bindestriche enthalten.',
        'slug_taken' => 'Dieser Slug ist bereits vergeben.',
        'tax_id_format' => 'Steuernummer muss 10 Ziffern haben.',
        'regon_format' => 'REGON muss 9 oder 14 Ziffern haben.',
        'terms' => 'Sie müssen die AGB akzeptieren.',
        'pwl_authorization_type_required' => 'Wählen Sie den PLW-Genehmigungstyp (T1 oder T2).',
        'provisioning_failed' => 'Konto konnte nicht erstellt werden — bitte später erneut versuchen.',
    ],
    'notify' => ['thanks_silent' => 'Danke — wir prüfen Ihre Anmeldung.'],
    'thanks' => [
        'title' => 'Vielen Dank für die Registrierung',
        'heading' => 'Antrag eingegangen!',
        'intro' => 'Das Konto für ":name" wurde erstellt und wartet auf Dokumentenverifizierung.',
        'step_1' => 'Wir prüfen Dokumente innerhalb von 2-3 Werktagen.',
        'step_2' => 'Nach erfolgreicher Prüfung senden wir den Magic-Link an Ihre E-Mail.',
        'step_3' => 'Ein 1-Monats-Trial startet dann automatisch.',
        'contact_hint' => 'Frage? Schreiben Sie an :email.',
        'cta_directory' => 'Spediteur-Verzeichnis ansehen',
    ],
];
