<?php

declare(strict_types=1);

return [
    'section' => [
        'title' => 'Przelewy24 — auto-link na ofertach',
        'description' => 'Auto-generowanie linka P24 (BLIK / przelew / karta) dla każdej '
            .'nowej oferty transportowej. Klient płaci bezpośrednio do Ciebie.',
        'disclaimer' => 'Przelewy24 to TWOJE konto, TWOJA umowa z DialCom24, TWOJE faktury. '
            .'Hovera tylko technicznie przekierowuje klienta do Twojego checkoutu — '
            .'wszystkie środki trafiają bezpośrednio na Twoje konto P24 (Hovera nie '
            .'pośredniczy w pieniądzach za transport — patrz docs/TRANSPORT.md §12 i §15.5).',
    ],

    'form' => [
        'label' => [
            'autopay_enabled' => 'Auto-generuj link P24 dla nowych ofert',
        ],
        'helper' => [
            'autopay_enabled' => 'Gdy włączone — przy tworzeniu oferty w PLN system '
                .'automatycznie wygeneruje sesję P24 i wstawi link jako payment_url. '
                .'Klient zobaczy przycisk „Zapłać Przelewy24" na publicznej stronie oferty.',
            'credentials_pointer' => 'Konfiguracja merchant_id / pos_id / crc / api_key '
                .'odbywa się w sekcji „Ustawienia płatności" (/app/payment-settings). '
                .'Zostawiamy jeden formularz dla wszystkich integracji P24 (booking, oferty).',
        ],
    ],

    'notify' => [
        'autopay_failed' => 'Nie udało się wygenerować linka Przelewy24',
    ],

    'return' => [
        'paid' => 'Płatność za ofertę {number} została zaksięgowana — dziękujemy!',
        'pending' => 'Płatność za ofertę {number} jest w trakcie weryfikacji. '
            .'Odśwież stronę za chwilę.',
        'unknown' => 'Nie znaleziono tej oferty. Skontaktuj się z transporterem.',
    ],
];
