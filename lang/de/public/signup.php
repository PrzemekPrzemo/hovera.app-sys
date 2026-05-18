<?php

declare(strict_types=1);

return [
    'title' => 'Konto anlegen',
    'title_stable' => 'Reitstall anlegen',
    'title_transporter' => 'Transportunternehmen anlegen',
    'thanks_title' => 'E-Mail prüfen',

    'heading' => 'Konto in hovera anlegen',
    'heading_stable' => 'Reitstall in hovera anlegen',
    'heading_transporter' => 'Transportunternehmen in hovera anlegen',
    'subtitle' => 'Füllen Sie 4 Felder aus und erhalten Sie eine E-Mail mit einem Link zur Passwortvergabe. Ohne Kreditkarte.',
    'subtitle_stable' => 'Füllen Sie 4 Felder aus und erhalten Sie eine E-Mail mit einem Link zur Passwortvergabe. Ohne Kreditkarte.',
    'subtitle_transporter' => 'Füllen Sie 4 Felder aus und erhalten Sie eine E-Mail mit einem Link zur Passwortvergabe. 30-Tage-Test — Pro-Tarif (5 Fahrzeuge, 10 Fahrer).',
    'back_to_choose' => 'Kontoart wechseln',

    'choose' => [
        'title' => 'Was betreiben Sie?',
        'heading' => 'Was betreiben Sie?',
        'subtitle' => 'Hovera bedient zwei unterschiedliche Geschäfte in einem Ökosystem. Wählen Sie das passende — das andere können Sie später hinzufügen.',
        'stable' => [
            'title' => 'Ich betreibe einen Reitstall',
            'price' => 'ab 0 € / Mon. · 30 Tage Test',
            'bullet_1' => 'Multi-Ressourcen-Kalender: Stunden, Training, Pflege',
            'bullet_2' => 'Kunden + Reitkarten + Auto-Abrechnung',
            'bullet_3' => 'Pferdekarte, Gesundheitsbuch, Fütterungsplan',
            'bullet_4' => 'Rechnungen + KSeF + Besitzerportal',
            'cta' => 'Stall registrieren →',
        ],
        'transporter' => [
            'title' => 'Ich betreibe ein Transportunternehmen',
            'price' => 'ab 35 € / Mon. · 30 Tage Test',
            'bullet_1' => 'Fahrzeuge + Fahrer + km/Kraftstoff-Preise',
            'bullet_2' => 'Routenkalkulator mit Karte (ORS/Mapbox/Google)',
            'bullet_3' => 'PDF-Angebote + Nummerierung + E-Mail',
            'bullet_4' => 'Marktplatz für Anfragen von Reitställen',
            'cta' => 'Unternehmen registrieren →',
        ],
    ],

    'trial_strong' => '🎉 30 Tage kostenlos',
    'trial_text' => 'Voller Funktionsumfang. Keine Karte. Keine automatische Umwandlung in einen kostenpflichtigen Tarif — Sie wählen erst dann, wenn Sie sicher sind, dass Sie bleiben möchten.',

    'label' => [
        'name' => 'Reitstall-Name',
        'name_stable' => 'Reitstall-Name',
        'name_transporter' => 'Firmenname',
        'slug' => 'URL des Reitstalls',
        'owner_name' => 'Ihr Vor- und Nachname',
        'owner_email' => 'E-Mail',
        'terms' => 'Ich akzeptiere die <a href="/regulamin" target="_blank">AGB</a> und die <a href="/polityka-prywatnosci" target="_blank">Datenschutzerklärung</a>',
    ],

    'placeholder' => [
        'name' => 'Reitstall Pegasus',
        'owner_name' => 'Anna Müller',
    ],

    'helper' => [
        'name' => 'So wird es im Panel und auf der öffentlichen Seite angezeigt.',
        'slug' => 'Nur Kleinbuchstaben, Ziffern und Bindestriche. Min. 3 Zeichen.',
        'owner_email' => 'Hierhin senden wir den Link zur Passwortvergabe.',
    ],

    'action' => [
        'submit' => 'Konto anlegen + 30 Tage kostenlos',
    ],

    'footer' => [
        'demo' => 'Zuerst Demo ansehen',
        'pricing' => 'Preise ansehen',
        'login' => 'Ich habe bereits ein Konto',
    ],

    'errors' => [
        'heading' => 'Bitte prüfen Sie das Formular:',
        'slug_format' => 'Die URL darf nur Kleinbuchstaben, Ziffern und Bindestriche enthalten (Bindestrich nicht am Anfang/Ende).',
        'slug_taken' => 'Diese URL ist bereits vergeben — versuchen Sie eine andere, z. B. mit Ortsangabe oder Kürzel.',
        'terms' => 'Sie müssen die AGB akzeptieren.',
        'provisioning_failed' => 'Auf unserer Seite ist etwas schiefgelaufen. Bitte versuchen Sie es in Kürze erneut oder schreiben Sie an support@hovera.app.',
    ],

    'thanks_heading' => '✓ Konto angelegt',
    'thanks_subtitle' => 'Der Reitstall :tenant wurde erstellt. Wir haben eine E-Mail mit dem Link zur Passwortvergabe gesendet.',
    'thanks_step_1' => 'Prüfen Sie Ihren Posteingang (Link 7 Tage gültig).',
    'thanks_step_2' => 'Klicken Sie in der E-Mail auf „Einladung annehmen".',
    'thanks_step_3' => 'Legen Sie ein Passwort fest — das ist Ihr erster Login zum Panel.',
    'thanks_step_4' => 'Sie landen im Panel /app — Sie haben 30 Tage Testphase ohne Einschränkungen.',
    'thanks_no_email' => 'Innerhalb von 5 Minuten keine E-Mail erhalten?',
    'thanks_no_email_help' => 'Prüfen Sie den Spam-Ordner. Falls weiterhin nichts da ist — schreiben Sie an support@hovera.app, wir helfen.',
];
