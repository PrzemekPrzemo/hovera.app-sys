<?php

declare(strict_types=1);

return [
    'action' => [
        'label' => 'Экспорт данных (post-trial)',
        'modal_heading' => 'Экспорт данных — :name',
        'modal_description' => 'Мы создаём ZIP, содержащий клиентов, лошадей, календарь (.ics), счета и meta.json. Файл будет скачан локально и удалён после отправки — на сервере он не остаётся.',
    ],
    'toast' => [
        'success_title' => 'Экспорт готов',
        'success_body' => 'Файл :file подготовлен для скачивания.',
        'failure_title' => 'Экспорт не удался',
    ],
];
