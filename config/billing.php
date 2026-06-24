<?php

return [
    'plans' => [
        'avancado' => [
            'name'        => 'Avançado',
            'price'       => 1490, // centavos (R$ 14,90)
            'price_id'    => env('STRIPE_PRICE_AVANCADO'),
            'group_limit' => null, // null = ilimitado
            'features'    => [
                'auto_post'         => true,
                'advanced_reminder' => true,
                'pdf_export'        => true,
                'custom_categories' => true,
            ],
        ],
    ],
];
