<?php

return [
    'sales' => [
        'max_items' => (int) env('SALE_MAX_ITEMS', 100),
        'currency_scale' => (int) env('SALE_CURRENCY_SCALE', 2),
        'payment_groups' => [
            'Cash' => ['100020002'],
            'Mobile Bank' => ['100020003'],
            'Bank' => ['100020004'],
        ],
    ],

    'vehicle_products' => [
        'max_assigned' => (int) env('VEHICLE_MAX_PRODUCTS', 50),
    ],
];
