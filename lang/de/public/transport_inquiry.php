<?php

declare(strict_types=1);

return [
    'title' => 'Pferdetransport-Anfrage',
    'heading' => 'Pferdetransport-Anfrage',
    'subtitle' => 'Füllen Sie das Formular aus — wir senden Ihre Anfrage an verifizierte Transporteure. Angebote kommen per E-Mail.',
    'errors_heading' => 'Bitte prüfen:',

    'label' => [
        'customer_name' => 'Name',
        'customer_email' => 'E-Mail',
        'customer_phone' => 'Telefon (optional)',
        'pickup_address' => 'Von (Abholung)',
        'dropoff_address' => 'Nach (Lieferung)',
        'preferred_date' => 'Bevorzugtes Datum',
        'preferred_time' => 'Uhrzeit (optional)',
        'flexible_date' => 'Datum flexibel (±2 Tage OK)',
        'horse_count' => 'Anzahl Pferde',
        'notes' => 'Zusätzliche Hinweise',
        'terms' => 'Ich willige in die Weitergabe meiner Daten an verifizierte Transporteure ein. <a href="/polityka-prywatnosci" target="_blank">Datenschutz</a>.',
    ],

    'placeholder' => [
        'pickup_address' => 'z. B. Reitstall, Hauptstr. 1, Berlin',
        'dropoff_address' => 'z. B. München, Sportstr. 1',
        'notes' => 'z. B. Zuchtpferde, Tiertransport-Zertifikat erforderlich...',
    ],

    'action' => [
        'submit' => 'Anfrage senden',
    ],

    'error' => [
        'geocoding' => 'Adresse nicht gefunden: :msg. Versuchen Sie Stadt + Straße.',
        'terms' => 'Sie müssen der Datenweitergabe zustimmen.',
    ],

    'thanks_title' => 'Anfrage erhalten',
    'thanks_heading' => 'Danke!',
    'thanks_body' => 'Wir haben Ihre Anfrage an Transportunternehmen gesendet. Angebote kommen an :email innerhalb von 24 Stunden.',
    'thanks_reference' => 'Referenznummer',
];
