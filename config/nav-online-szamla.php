<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | "test" or "production". Defaults to the test environment so that nothing
    | reaches the production NAV system without explicit configuration.
    |
    */

    'environment' => env('NAV_ENVIRONMENT', 'test'),

    'urls' => [
        'test' => 'https://api-test.onlineszamla.nav.gov.hu/invoiceService/v3',
        'production' => 'https://api.onlineszamla.nav.gov.hu/invoiceService/v3',
    ],

    /*
    |--------------------------------------------------------------------------
    | Technical User
    |--------------------------------------------------------------------------
    |
    | Credentials of the default taxpayer's technical user, created on the
    | Online Számla web interface. Multi-taxpayer applications can resolve
    | credentials per tenant instead.
    |
    */

    'credentials' => [
        'login' => env('NAV_USERNAME'),
        'password' => env('NAV_PASSWORD'),
        'tax_number' => env('NAV_TAX_NUMBER'),
        'sign_key' => env('NAV_SIGN_KEY'),
        'exchange_key' => env('NAV_EXCHANGE_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Software
    |--------------------------------------------------------------------------
    |
    | Identification of the invoicing software, sent with every request.
    | The software ID is 18 characters of [0-9A-Z-].
    |
    */

    'software' => [
        'id' => env('NAV_SOFTWARE_ID'),
        'name' => env('NAV_SOFTWARE_NAME', env('APP_NAME', 'Laravel')),
        'operation' => env('NAV_SOFTWARE_OPERATION', 'ONLINE_SERVICE'),
        'main_version' => env('NAV_SOFTWARE_MAIN_VERSION', '1.0'),
        'dev_name' => env('NAV_SOFTWARE_DEV_NAME'),
        'dev_contact' => env('NAV_SOFTWARE_DEV_CONTACT'),
        'dev_country_code' => env('NAV_SOFTWARE_DEV_COUNTRY_CODE', 'HU'),
        'dev_tax_number' => env('NAV_SOFTWARE_DEV_TAX_NUMBER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    |
    | Retries apply to queries and tokenExchange on transient errors only.
    | Submissions are never retried automatically.
    |
    */

    'http' => [
        'timeout' => (int) env('NAV_HTTP_TIMEOUT', 30),
        'connect_timeout' => (int) env('NAV_HTTP_CONNECT_TIMEOUT', 10),
        'retries' => (int) env('NAV_HTTP_RETRIES', 2),
        'retry_delay_ms' => (int) env('NAV_HTTP_RETRY_DELAY_MS', 500),
    ],

    'validate_xsd' => (bool) env('NAV_VALIDATE_XSD', true),

    'log_channel' => env('NAV_LOG_CHANNEL'),

];
