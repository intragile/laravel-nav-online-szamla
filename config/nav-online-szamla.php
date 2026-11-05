<?php

use IntrAgile\NavOnlineSzamla\Models\NavTransaction;
use IntrAgile\NavOnlineSzamla\Models\NavTransactionItem;
use IntrAgile\NavOnlineSzamla\Tenancy\ConfigCredentialsResolver;

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

    /*
    |--------------------------------------------------------------------------
    | Tenants
    |--------------------------------------------------------------------------
    |
    | Applications that submit invoices for several taxpayers address them by a
    | tenant key. The default resolver reads "tenants.{key}" (same keys as
    | "credentials" above); bind your own CredentialsResolver implementation
    | to load credentials from elsewhere, e.g. an encrypted database column.
    |
    */

    'credentials_resolver' => ConfigCredentialsResolver::class,

    'tenants' => [
        // 'acme' => [
        //     'login' => env('ACME_NAV_USERNAME'),
        //     'password' => env('ACME_NAV_PASSWORD'),
        //     'tax_number' => env('ACME_NAV_TAX_NUMBER'),
        //     'sign_key' => env('ACME_NAV_SIGN_KEY'),
        //     'exchange_key' => env('ACME_NAV_EXCHANGE_KEY'),
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Client-side Validation
    |--------------------------------------------------------------------------
    |
    | Queued submissions are checked against NAV's blocking business rules
    | before they are sent. List error codes here to skip individual rules.
    |
    */

    'validation' => [
        'enabled' => (bool) env('NAV_VALIDATE_INVOICES', true),
        'skip' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queued Submission
    |--------------------------------------------------------------------------
    |
    | "polling.delays" are the seconds to wait before each status query; the
    | last value repeats until "polling.max_attempts" is reached.
    |
    | After a timeout the outcome of a submission is unknown. Instead of sending
    | it again, the package looks for it with queryTransactionList and
    | queryInvoiceCheck "reconciliation.delay" seconds later, and resubmits only
    | when NAV demonstrably never received it.
    |
    */

    'queue' => [
        'connection' => env('NAV_QUEUE_CONNECTION'),
        'name' => env('NAV_QUEUE'),
    ],

    'polling' => [
        'delays' => [5, 10, 20, 30, 60, 120, 300, 600, 1800],
        'max_attempts' => (int) env('NAV_POLLING_MAX_ATTEMPTS', 30),
    ],

    'reconciliation' => [
        'delay' => (int) env('NAV_RECONCILIATION_DELAY', 60),
        'max_attempts' => (int) env('NAV_RECONCILIATION_MAX_ATTEMPTS', 5),
        'resubmit' => (bool) env('NAV_RECONCILIATION_RESUBMIT', true),
        'max_submissions' => (int) env('NAV_MAX_SUBMISSIONS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Log
    |--------------------------------------------------------------------------
    |
    | Every queued submission is recorded together with the exact data sent.
    | Set "run_migrations" to false after publishing the migration to customise
    | it.
    |
    */

    'transactions' => [
        'run_migrations' => true,
        'connection' => env('NAV_DB_CONNECTION'),
        'table' => 'nav_transactions',
        'items_table' => 'nav_transaction_items',
        'model' => NavTransaction::class,
        'item_model' => NavTransactionItem::class,
    ],

];
