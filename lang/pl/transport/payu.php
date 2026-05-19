<?php

declare(strict_types=1);

return [
    'section' => [
        'title' => 'PayU — auto-link na ofertach',
        'description' => 'Auto-generowanie linka PayU (BLIK / przelew / karta / Apple Pay / Google Pay) '
            .'dla każdej nowej oferty transportowej. Klient płaci bezpośrednio do Ciebie.',
        'disclaimer' => 'PayU to TWOJE konto, TWOJA umowa z PayU.pl S.A., TWOJE faktury. '
            .'Hovera tylko technicznie przekierowuje klienta do Twojego checkoutu — '
            .'wszystkie środki trafiają bezpośrednio na Twoje konto PayU (Hovera nie '
            .'pośredniczy w pieniądzach za transport — patrz docs/TRANSPORT.md §12 i §16).',
    ],

    'form' => [
        'label' => [
            'autopay_enabled' => 'Auto-generuj link PayU dla nowych ofert',
        ],
        'helper' => [
            'autopay_enabled' => 'Gdy włączone — przy tworzeniu oferty w PLN system '
                .'automatycznie wygeneruje order PayU i wstawi link jako payment_url. '
                .'Klient zobaczy przycisk „Zapłać PayU" na publicznej stronie oferty.',
            'credentials_pointer' => 'Konfiguracja pos_id / oauth_client_id / oauth_client_secret '
                .'/ md5_key odbywa się w sekcji „Ustawienia płatności" (/app/payment-settings). '
                .'Zostawiamy jeden formularz dla wszystkich integracji PayU (oferty + inne).',
        ],
    ],

    'notify' => [
        'autopay_failed' => 'Nie udało się wygenerować linka PayU',
    ],

    'return' => [
        'paid' => 'Płatność za ofertę {number} została zaksięgowana — dziękujemy!',
        'pending' => 'Płatność za ofertę {number} jest w trakcie weryfikacji. '
            .'Odśwież stronę za chwilę.',
        'unknown' => 'Nie znaleziono tej oferty. Skontaktuj się z transporterem.',
    ],
];
