<?php

declare(strict_types=1);

/*
 * Strings używane WYŁĄCZNIE w pre-rendered OG image (1200x630 PNG) generowanym
 * przez TransporterOgImageController. Osobny namespace od transporter_profile.php
 * bo OG image to inny kontekst — krótszy, brak HTML, tylko 2 stringi.
 */
return [
    // Pokazywane gdy tenant nie ma ustawionego settings.public_profile.tagline
    'default_tagline' => 'Profesjonalny transport koni',

    // Stopka w dolnej części obrazka, ~22px wysokości.
    'footer' => 'Profesjonalny transport koni · hovera.app',
];
