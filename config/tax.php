<?php

declare(strict_types=1);

$tablePrefix = (string) env('TAX_TABLE_PREFIX', env('COMMERCE_TABLE_PREFIX', ''));

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'json_column_type' => env('TAX_JSON_COLUMN_TYPE', 'jsonb'),
        'tables' => [
            'tax_zones' => $tablePrefix . 'tax_zones',
            'tax_rates' => $tablePrefix . 'tax_rates',
            'tax_classes' => $tablePrefix . 'tax_classes',
            'tax_exemptions' => $tablePrefix . 'tax_exemptions',
        ],
        'table_prefix' => $tablePrefix,
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'currency' => env('TAX_DEFAULT_CURRENCY', 'MYR'),
        'prices_include_tax' => env('TAX_PRICES_INCLUDE_TAX', false),
        'calculate_tax_on_shipping' => env('TAX_ON_SHIPPING', true),
        'round_per_rate' => true,
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
            'auto_assign_on_create' => env('TAX_OWNER_AUTO_ASSIGN', true),
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
