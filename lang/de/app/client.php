<?php

declare(strict_types=1);

return [
    'types' => [
        'individual' => 'Privatperson',
        'family' => 'Familie',
        'organisation' => 'Firma / Organisation',
    ],
    'types_short' => [
        'individual' => 'Privatp.',
        'family' => 'Familie',
        'organisation' => 'Firma',
    ],

    'form' => [
        'section' => [
            'data' => 'Kundendaten',
            'armir' => 'Identifikation des Pferdebesitzers (ARMiR)',
            'armir_description' => 'Erforderlich für Besitzer von Pferden, die im polnischen zentralen Equidenregister geführt werden. EP (Erzeugernummer der ARMiR) — falls nicht vorhanden, geben Sie die PESEL ein.',
            'address' => 'Adresse',
            'rodo' => 'DSGVO',
            'notes' => 'Notizen',
            'sport' => 'Sport (LiveJumping)',
            'sport_help' => 'Fügen Sie die URL des Reiterprofils von LiveJumping.com ein — wir zeigen Statistiken und aktuelle Starts an.',
        ],
        'label' => [
            'type' => 'Typ',
            'name' => 'Vor- und Nachname / Name',
            'phone' => 'Telefon',
            'tax_id' => 'USt-IdNr. / VAT-ID',
            'armir_producer_id' => 'EP-Nr. (ARMiR-Erzeugernummer)',
            'armir_producer_id_placeholder' => 'z. B. 026123456789',
            'pesel' => 'PESEL',
            'street' => 'Straße und Hausnummer',
            'postal_code' => 'PLZ',
            'city' => 'Stadt',
            'country' => 'Land',
            'rodo_consent_at' => 'DSGVO-Einwilligung erteilt',
            'rodo_consent_source' => 'Quelle der Einwilligung',
            'notes' => 'Interne Notizen',
            'livejumping_profile_url' => 'URL des LiveJumping-Profils',
            'livejumping_palmares' => 'Palmarès',
        ],
        'helper' => [
            'armir_producer_id' => 'Bei der ARMiR vergebene Erzeugernummer bei der Pferderegistrierung.',
            'pesel' => 'Nur eingeben, wenn der Besitzer keine EP-Nummer in der ARMiR hat.',
            'livejumping_profile_url' => 'Kopieren Sie die URL der Profilseite von livejumping.com — z. B. https://livejumping.com/rider/12345/anna-kowalska',
            'livejumping_no_profile' => 'Fügen Sie oben eine LJ-Profil-URL ein, um das Palmarès zu sehen.',
            'livejumping_fetch_failed' => 'Daten konnten nicht von LiveJumping abgerufen werden (URL prüfen oder später erneut versuchen).',
        ],
        'stats' => [
            'starts' => 'Starts',
            'wins' => 'Siege',
            'placings' => 'Top-Platzierungen',
            'ranking_points' => 'Ranglistenpunkte',
            'recent_results' => 'Aktuelle Ergebnisse',
        ],
        'gus' => [
            'lookup_label' => 'Aus GUS abrufen',
            'invalid_nip' => 'Ungültige NIP (Prüfsumme).',
            'not_found' => 'Firma nicht in GUS gefunden.',
            'success' => 'Daten aus GUS übernommen.',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'type' => 'Typ',
            'phone' => 'Telefon',
            'horses_count' => 'Pferde',
            'rodo' => 'DSGVO',
            'created_at' => 'Hinzugefügt',
        ],
    ],

    'action' => [
        'issue_portal_link' => [
            'label' => 'Portal-Link kopieren',
            'modal_heading' => 'Anmeldelink für :name generieren?',
            'modal_description' => 'Erstellt einen einmaligen Magic Link (TTL 30 Min.). Sie können ihn kopieren und dem Kunden manuell senden, z. B. per SMS oder Messenger. E-Mail nicht erforderlich.',
            'success_title' => 'Anmeldelink erstellt',
        ],
        'email_portal_link' => [
            'label' => 'Link per E-Mail senden',
            'modal_heading' => 'Anmeldelink an :name senden?',
            'modal_description' => 'Wir senden eine E-Mail mit dem Anmeldelink an :email. Der Link ist 30 Minuten lang einmalig gültig.',
            'success_title' => 'Link gesendet',
            'success_body' => 'E-Mail mit Anmeldelink an :email gesendet.',
            'no_email' => 'Der Kunde hat keine E-Mail-Adresse im Profil.',
        ],
    ],
];
