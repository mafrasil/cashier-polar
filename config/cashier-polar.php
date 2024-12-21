<?php

// config for Mafrasil/CashierPolar
return [
    /*
    |--------------------------------------------------------------------------
    | Polar Keys
    |--------------------------------------------------------------------------
    |
    | The Polar API key and organization ID are used to authenticate with the Polar
    | API. You can find your API key and organization ID in your Polar dashboard.
    |
     */
    'key' => env('POLAR_API_KEY'),
    'organization_id' => env('POLAR_ORGANIZATION_ID'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | This secret is used to verify that webhooks are actually coming from Polar.
    | You can find your webhook secret in your Polar dashboard.
    |
     */
    'webhook_secret' => env('POLAR_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Sandbox Mode
    |--------------------------------------------------------------------------
    |
    | This option controls whether the integration should use Polar's sandbox
    | environment for testing. When enabled, all API requests will be directed
    | to the sandbox URL instead of the production URL.
    |
     */
    'sandbox' => env('POLAR_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | API URLs
    |--------------------------------------------------------------------------
    |
    | These are the base URLs for the Polar API. They will be automatically
    | selected based on whether sandbox mode is enabled.
    |
     */
    'urls' => [
        'production' => 'https://api.polar.sh/v1',
        'sandbox' => 'https://sandbox-api.polar.sh/v1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | This is the default currency that will be used when generating charges
    | from your application.
    |
     */
    'currency' => env('CASHIER_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Path
    |--------------------------------------------------------------------------
    |
    | This is the path that will be used to receive webhooks from Polar.
    |
     */
    'path' => env('POLAR_PATH', 'webhooks/polar'),
];
