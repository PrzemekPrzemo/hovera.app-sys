<?php

declare(strict_types=1);

return [
    'navigation' => 'hovera-Firmendaten',
    'title' => 'hovera-Firmendaten (Verkäufer auf Rechnungen)',

    'section' => [
        'identity' => 'Identifikationsdaten',
        'identity_help' => 'Werden als Verkäuferdaten auf SaaS-Rechnungen verwendet, die an Reitställe ausgestellt werden (KSeF, PDF, FA(3) XML).',
        'address' => 'Geschäftsanschrift',
        'contact' => 'Kontakt',
        'bank' => 'Bankverbindung',
        'bank_help' => 'Die IBAN erscheint auf Rechnungen als Überweisungskonto (wenn der Kunde per klassischer Überweisung statt Stripe/P24 zahlt).',
    ],

    'field' => [
        'name' => 'Firmenname',
        'legal_form' => 'Rechtsform',
        'nip' => 'NIP',
        'regon' => 'REGON',
        'krs' => 'KRS',
        'court' => 'Registergericht',
        'capital' => 'Stammkapital',
        'street' => 'Straße und Hausnummer',
        'postal_code' => 'PLZ',
        'city' => 'Stadt',
        'country' => 'Land (ISO-Code)',
        'email' => 'E-Mail',
        'phone' => 'Telefon',
        'bank_name' => 'Bankname',
        'iban' => 'IBAN',
        'swift' => 'SWIFT/BIC',
    ],

    'action' => [
        'save_button' => 'Firmendaten speichern',
        'saved' => 'Firmendaten gespeichert.',
    ],
];
