<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'tables' => [
            'tax_zones' => 'tax_zones',
            'tax_rates' => 'tax_rates',
            'tax_classes' => 'tax_classes',
            'tax_exemptions' => 'tax_exemptions',
        ],

        'json_column_type' => env('TAX_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'prices_include_tax' => env('TAX_PRICES_INCLUDE_TAX', false),
        'calculate_tax_on_shipping' => env('TAX_ON_SHIPPING', true),
        'round_at_subtotal' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'enabled' => env('TAX_ENABLED', true),

        'owner' => [
            'enabled' => env('TAX_OWNER_ENABLED', false),
            'include_global' => false,
        ],

        'zone_resolution' => [
            'use_customer_address' => true,
            'address_priority' => 'shipping',
            'unknown_zone_behavior' => 'default',
            'fallback_zone_id' => null,
        ],

        'exemptions' => [
            'enabled' => true,
        ],
    ],
];
