<?php

declare(strict_types=1);

return [
    'section' => [
        'identification' => 'Идентификация',
        'capacity' => 'Вместимость и масса',
        'equipment' => 'Оснащение',
        'other' => 'Прочее',
    ],

    'form' => [
        'label' => [
            'vehicle_type' => 'Тип ТС',
            'name' => 'Название транспорта',
            'registration_plate' => 'Гос. номер',
            'year_of_manufacture' => 'Год выпуска',
            'capacity_horses' => 'Вместимость (лошадей)',
            'gross_weight_kg' => 'Полная масса',
            'payload_kg' => 'Грузоподъёмность',
            'has_air_suspension' => 'Пневмоподвеска',
            'has_camera' => 'Камера в отсеке',
            'has_climate_control' => 'Климат-контроль',
            'is_active' => 'Активен',
            'sort_order' => 'Порядок',
            'notes' => 'Примечания',
        ],
        'helper' => [
            'vehicle_type' => 'У прицепа нет двигателя и расхода топлива — в предложении он комбинируется с ведущим ТС (truck).',
        ],
        'placeholder' => [
            'name' => 'напр. Volvo FH16 — большой коневоз',
        ],
        'suffix' => [
            'horses' => 'лош.',
        ],
    ],

    'table' => [
        'column' => [
            'vehicle_type' => 'Тип',
            'name' => 'Название',
            'registration_plate' => 'Номер',
            'capacity_horses' => 'Лош.',
            'gross_weight_kg' => 'Масса',
            'is_active' => 'Активен',
        ],
    ],
];
