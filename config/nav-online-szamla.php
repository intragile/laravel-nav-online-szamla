<?php

return [

    /*
    | A NAV környezet: "test" (api-test.onlineszamla.nav.gov.hu) vagy "production".
    | Az alapértelmezés szándékosan a teszt: éles beküldés csak kifejezett beállításra történhet.
    */
    'environment' => env('NAV_ENVIRONMENT', 'test'),

    'urls' => [
        'test' => 'https://api-test.onlineszamla.nav.gov.hu/invoiceService/v3',
        'production' => 'https://api.onlineszamla.nav.gov.hu/invoiceService/v3',
    ],

    /*
    | A technikai felhasználó adatai. Több adóalanynál (tenant) ezt egy saját
    | CredentialsResolver váltja ki; ez az alapértelmezett, egy adóalanyos eset.
    */
    'credentials' => [
        'login' => env('NAV_LOGIN'),
        'password' => env('NAV_PASSWORD'),
        'tax_number' => env('NAV_TAX_NUMBER'),
        'sign_key' => env('NAV_SIGN_KEY'),
        'exchange_key' => env('NAV_EXCHANGE_KEY'),
    ],

    /*
    | A számlázó szoftver adatai (a NAV minden kérésben elvárja).
    | software_id: 18 karakter, [0-9A-Z\-].
    */
    'software' => [
        'id' => env('NAV_SOFTWARE_ID'),
        'name' => env('NAV_SOFTWARE_NAME', env('APP_NAME', 'Laravel')),
        'operation' => env('NAV_SOFTWARE_OPERATION', 'ONLINE_SERVICE'), // vagy LOCAL_SOFTWARE
        'main_version' => env('NAV_SOFTWARE_MAIN_VERSION', '1.0'),
        'dev_name' => env('NAV_SOFTWARE_DEV_NAME'),
        'dev_contact' => env('NAV_SOFTWARE_DEV_CONTACT'),
        'dev_country_code' => env('NAV_SOFTWARE_DEV_COUNTRY_CODE', 'HU'),
        'dev_tax_number' => env('NAV_SOFTWARE_DEV_TAX_NUMBER'),
    ],

    'http' => [
        'timeout' => (int) env('NAV_HTTP_TIMEOUT', 30),
        'connect_timeout' => (int) env('NAV_HTTP_CONNECT_TIMEOUT', 10),
        // Csak technikai hibánál (hálózat, 5xx) ismétel; üzleti hibánál soha.
        'retries' => (int) env('NAV_HTTP_RETRIES', 2),
        'retry_delay_ms' => (int) env('NAV_HTTP_RETRY_DELAY_MS', 500),
    ],

    // Kimenő XML validálása a hivatalos XSD-k ellen beküldés előtt.
    'validate_xsd' => env('NAV_VALIDATE_XSD', true),

    'log_channel' => env('NAV_LOG_CHANNEL'),
];
