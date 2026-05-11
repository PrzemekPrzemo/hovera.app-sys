<?php

declare(strict_types=1);

return [
    'navigation' => 'Счета SaaS',
    'model' => 'Счёт SaaS',
    'model_plural' => 'Счета SaaS',

    'kind' => [
        'regular' => 'Обычный (счёт-фактура)',
        'proforma' => 'Проформа',
        'correction' => 'Корректировка',
    ],

    'form' => [
        'section' => [
            'basics' => 'Основные данные',
            'amounts' => 'Суммы',
            'dates' => 'Даты',
        ],
        'label' => [
            'tenant' => 'Конюшня (покупатель)',
            'number' => 'Номер счёта',
            'kind' => 'Тип',
            'subtotal' => 'Без НДС (гроши)',
            'vat_rate' => 'Ставка НДС (%)',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Номер',
            'tenant' => 'Конюшня',
            'issued_at' => 'Выставлен',
            'total' => 'Сумма с НДС',
            'status' => 'Статус',
            'ksef_status' => 'KSeF',
        ],
    ],

    'action' => [
        'issue_manual' => 'Выставить счёт вручную',
        'send_p24_link' => 'Отправить ссылку P24',
        'p24_link_generated' => 'Ссылка Przelewy24 сгенерирована',
        'p24_link_failed' => 'Не удалось сгенерировать ссылку P24',
        'send_to_ksef' => 'Отправить в KSeF',
        'ksef_sent' => 'Отправлено в KSeF',
        'ksef_failed' => 'Отправка в KSeF не удалась',
        'ksef_reference' => 'Референсный номер KSeF',
        'download_pdf' => 'Скачать PDF',
        'pdf_stub_title' => 'Генерация PDF приостановлена',
        'pdf_stub_body' => 'Полная генерация PDF счёта требует dompdf/snappy — будет добавлена в следующем PR.',
        'resend_email' => 'Отправить email повторно',
    ],

    'p24_return' => [
        'paid' => 'Оплата счёта :number подтверждена.',
        'pending' => 'Спасибо! Оплата счёта :number проверяется — обычно это занимает несколько минут.',
        'unknown' => 'Счёт не распознан — проверьте письмо с подтверждением.',
    ],
];
