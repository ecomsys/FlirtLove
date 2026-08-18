<?php

return [
    'limits' => [
        // Дефолтные лимиты для бесплатных юзеров
        'free' => [
            'superlikes' => 5,
            'boosts' => 0,
        ],
        // Лимиты для премиумов
        'premium' => [
            'superlikes' => 10,
            'boosts' => 1,
        ],
    ],
];