<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reserved ERP Categories
    |--------------------------------------------------------------------------
    |
    | These codes are permanent business identifiers. Database category names
    | may be edited, but the configured codes must never be changed, reused,
    | or deleted.
    |
    */
    'categories' => [
        1001 => 'Oil',
        1002 => 'Gas',
        1003 => 'Lubricant & Accessories',
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Employee File Uploads
    |--------------------------------------------------------------------------
    |
    | Employee files are stored on the configured Laravel filesystem disk.
    | Database records contain only paths relative to the selected disk.
    | Upload size limits are expressed in kilobytes.
    |
    */
    'employee_uploads' => [
        'disk' => env('EMPLOYEE_UPLOAD_DISK', 'public'),
        'directory' => env('EMPLOYEE_UPLOAD_DIRECTORY', 'employees'),
        'image_max_kb' => (int) env('EMPLOYEE_IMAGE_MAX_KB', 5120),
        'nid_max_kb' => (int) env('EMPLOYEE_NID_MAX_KB', 10240),
    ],
];
