# Laravel NAV Online Számla

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

[![Tests](https://github.com/intragile/laravel-nav-online-szamla/actions/workflows/tests.yml/badge.svg)](https://github.com/intragile/laravel-nav-online-szamla/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/intragile/laravel-nav-online-szamla.svg)](https://packagist.org/packages/intragile/laravel-nav-online-szamla)
[![PHP](https://img.shields.io/packagist/php-v/intragile/laravel-nav-online-szamla.svg)](https://packagist.org/packages/intragile/laravel-nav-online-szamla)
[![License](https://img.shields.io/packagist/l/intragile/laravel-nav-online-szamla.svg)](LICENSE)

A complete, Laravel-native client for the Hungarian tax authority's **NAV Online Számla
(Online Invoice) v3.0** interface.

[Magyar nyelvű leírás → README.hu.md](README.hu.md)

- **All 10 operations**, with typed request and response objects. You never touch raw XML.
- **Typed invoice model** generated from the official XSDs, plus a **builder** that computes
  line amounts, VAT summaries and totals. It covers CREATE/MODIFY/STORNO, simplified,
  aggregate, foreign-currency and advance invoices.
- **Client-side checks before sending:** every document is validated against the bundled
  official XSDs, and NAV's blocking business rules are run where the data can decide them.
- **Built on Laravel's HTTP client**, so `Http::fake()`, Telescope and Pulse all see NAV traffic.
- **Queued submission with a transaction log.** Status polling uses backoff, and an event is
  fired at every step. After a timeout the package **reconciles instead of resending
  blindly**, so an invoice is never reported twice.
- **Multiple taxpayers (tenants)**, artisan commands, and `Nav::fake()` with assertions.

## Requirements

- PHP 8.3+ with `bcmath`, `dom`, `libxml`, `openssl`
- Laravel 12 or 13

## Installation

```bash
composer require intragile/laravel-nav-online-szamla
php artisan vendor:publish --tag=nav-online-szamla-config
php artisan migrate
```

Create a technical user on the Online Számla web interface (use the
[test environment](https://onlineszamla-test.nav.gov.hu) first) and configure it:

```dotenv
NAV_ENVIRONMENT=test            # "production" only when you mean it
NAV_USERNAME=...
NAV_PASSWORD=...
NAV_TAX_NUMBER=12345678         # any format; the first 8 digits are used
NAV_SIGN_KEY=...
NAV_EXCHANGE_KEY=...

NAV_SOFTWARE_ID=                # see below
NAV_SOFTWARE_DEV_NAME="Your Company Ltd."
NAV_SOFTWARE_DEV_CONTACT=dev@example.com
NAV_SOFTWARE_DEV_TAX_NUMBER=12345678
```

Every request identifies your software with an 18-character `softwareId`. Generate it **once**
and keep it: the ID identifies the software in every installation.

```bash
php artisan nav:software-id --write    # stores NAV_SOFTWARE_ID in .env
php artisan nav:test-connection        # token exchange + taxpayer query with your credentials
```

## Quick start

```php
use IntrAgile\NavOnlineSzamla\Facades\Nav;

$taxpayer = Nav::queryTaxpayer('87654321-2-13');   // ?Taxpayer
$taxpayer?->name;
$taxpayer?->headquarters()?->city;
```

Build an invoice and queue it for submission:

```php
use IntrAgile\NavOnlineSzamla\Enums\InvoiceAppearance;
use IntrAgile\NavOnlineSzamla\Enums\PaymentMethod;
use IntrAgile\NavOnlineSzamla\Invoice\InvoiceBuilder;
use IntrAgile\NavOnlineSzamla\Invoice\LineItem;
use IntrAgile\NavOnlineSzamla\Invoice\Model\UnitOfMeasure;
use IntrAgile\NavOnlineSzamla\Invoice\Party;
use IntrAgile\NavOnlineSzamla\Invoice\Vat;

$invoice = InvoiceBuilder::create('INV-2026-0001')
    ->issueDate('2026-11-05')
    ->deliveryDate('2026-11-05')
    ->payment(PaymentMethod::Transfer, '2026-11-20')
    ->appearance(InvoiceAppearance::Electronic)
    ->supplier(Party::supplier(
        '12345678-2-41',
        'Eladó Kft.',
        Party::address('HU', '1011', 'Budapest', 'Fő utca 1.'),
        '11111111-22222222-33333333',
    ))
    ->customer(Party::domesticCustomer(
        '87654321-2-13',
        'Vevő Kft.',
        Party::address('HU', '2000', 'Szentendre', 'Kossuth tér 2.'),
    ))
    ->line(LineItem::product('Laptop')->quantity(2, UnitOfMeasure::Piece)->unitPrice('250000')->vat(Vat::rate(27)))
    ->line(LineItem::product('Könyv')->quantity(3, UnitOfMeasure::Piece)->unitPrice('4990')->vat(Vat::rate(5)))
    ->line(LineItem::service('Kiszállítás')->netAmount('2500')->vat(Vat::rate(27)));

$transaction = Nav::submit($invoice, subject: $order);   // NavTransaction, state "pending"
```

`Nav::submit()` stores the exact XML in the transaction log, links it to your model, and
dispatches the submission job. Listen to the events to learn the outcome (see
[Queued submission](#queued-submission)).

To work synchronously instead:

```php
$transactionId = Nav::manageInvoice($invoice->submission());
$status = Nav::queryTransactionStatus($transactionId);

if ($status->isFinished()) {
    $result = $status->results[0];
    $result->isAccepted();        // DONE
    $result->warnings();          // list<BusinessValidationMessage>
}
```

## Invoice builder

The builder computes every amount: line net, VAT and gross amounts, HUF amounts for
foreign-currency invoices, the VAT-rate summary and the invoice totals. Rounding is
half away from zero, using `bcmath` decimals throughout, never floats.

| Need | API |
|---|---|
| Line types | `LineItem::product()`, `::service()`, `::other()` |
| Quantity / price | `->quantity('1.5', UnitOfMeasure::Kilogram)`, `->unitPrice('400')`, or `->netAmount()`, `->grossAmount()` |
| VAT | `Vat::rate(27)`, `Vat::exempt('AAM', 'Alanyi adómentes')`, `Vat::outOfScope(...)`, `Vat::domesticReverseCharge()`, `Vat::marginScheme(...)`, `Vat::noCharge()`, `Vat::content(...)` (simplified invoices) |
| Discount | `->discount('Árengedmény', rate: '0.1')` |
| Advance | `->asAdvance()`, `->settlesAdvance('ELO-1', '2026-10-01', '392.17')` |
| Customers | `Party::domesticCustomer()`, `::euCustomer()`, `::thirdStateCustomer()`, `::otherCustomer()`, `::privatePerson()` |
| Invoice kinds | `->simplified()`, `->aggregate()`, `->currency('EUR', '392.17')`, `->completeElectronicInvoice()` |
| Modification | `InvoiceBuilder::modify('INV-1-M', 'INV-1', modificationIndex: 1)`, `BatchModification::of(...)` |

`build()` returns the typed `InvoiceData` model after validating it, and `submission()`
returns the `InvoiceSubmission` that is sent.

### Cancelling an invoice (storno)

`stornoOf()` reverses every line of an invoice with exactly negated amounts, keeping its
parties and details. The easiest way to get the original is from NAV:

```php
use IntrAgile\NavOnlineSzamla\Requests\InvoiceNumberQuery;

$original = Nav::queryInvoiceData(InvoiceNumberQuery::outbound('INV-2026-0001'))?->invoice();

$storno = InvoiceBuilder::stornoOf($original, 'INV-2026-0001-S', modificationIndex: 1, issueDate: '2026-11-05');
Nav::submit($storno);
```

## Validation before sending

NAV rejects many invoices only after processing them, minutes later, with an ABORTED status.
The `InvoiceValidator` checks the blocking rules (chapter 3.3.2 of the specification) that the
submitted data can decide, so most mistakes surface as an exception before anything is sent:

```php
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceValidationException;

try {
    $invoice->build();
} catch (InvoiceValidationException $e) {
    $e->codes();          // ['INVOICE_ISSUE_DATE_LATE']
    $e->violations;       // list<Violation> with code, message and path
}
```

A few rules depend on facts the invoice cannot show. If you know NAV accepts your case,
skip the rule for one invoice or for the whole application:

```php
use IntrAgile\NavOnlineSzamla\Invoice\Validation\InvoiceValidator;

$invoice->validateWith((new InvoiceValidator)->skip('MISSING_CUSTOMER_DOMESTIC_TAXNUMBER'));
```

```php
// config/nav-online-szamla.php
'validation' => ['enabled' => true, 'skip' => ['MISSING_CUSTOMER_DOMESTIC_TAXNUMBER']],
```

The queue job also validates every submission against the **authenticated** tax number,
which catches `SUPPLIER_TAX_NUMBER_MISMATCH` before NAV does.

## Queued submission

```
Nav::submit() ─► pending ─► sending ─► processing ─► finished   (every item DONE or ABORTED)
                    │          │            └─────► abandoned  (polling exhausted)
                    │          ├──► rejected    (NAV refused the request itself)
                    │          └──► unconfirmed ─► reconciliation ─► processing | pending | abandoned
                    └──► rejected (client-side validation)
```

Each transaction is claimed atomically before it is sent, so duplicate or retried jobs never
send it twice. `manageInvoice` is never retried automatically.

**Reconciliation instead of resending blindly.** A timeout or lost response leaves NAV's
outcome unknown: it may have accepted the invoice. The transaction is marked as
`unconfirmed`, and after `reconciliation.delay` seconds the package:

1. lists the transactions of the sending window (`queryTransactionList`),
2. fetches the original request of each candidate and compares it with the logged XML,
3. resumes polling under the found transaction ID, or
4. if nothing matches and `queryInvoiceCheck` does not know any of the invoices either, in two
   checks spaced apart, resubmits. This is the only case in which anything is resent.

A NAV error response with a permanent error code rejects the transaction. A response without a
NAV error code, such as a proxy page or a truncated body, leaves the outcome open and goes to
reconciliation.

| Event | When |
|---|---|
| `TransactionSubmitted` | NAV issued a transaction ID |
| `InvoiceAccepted` / `InvoiceRejected` | an item finished as DONE / ABORTED (with NAV's messages) |
| `TransactionFinished` | every item finished |
| `TransactionRejected` | never accepted: client-side validation or NAV error; see `error_code` |
| `TransactionUnconfirmed` | outcome unknown; reconciliation scheduled |
| `TransactionReconciled` | reconciliation found the transaction (`found: true`) or queued a resubmission |
| `TransactionAbandoned` | automatic handling gave up; needs attention |

```php
use IntrAgile\NavOnlineSzamla\Events\InvoiceAccepted;

Event::listen(function (InvoiceAccepted $event) {
    $event->item->subject?->update(['nav_status' => 'reported']);
    $event->result->warnings();
});
```

Configure the queue connection and name, the polling backoff (`polling.delays`, where the last
value repeats) and the reconciliation limits in `config/nav-online-szamla.php`. The `sync`
driver works, but it waits out the delays in the current process. Use a real queue in production.

**Operating it safely:**

- Run workers that enforce job timeouts (the pcntl extension is enabled by default on Linux).
  Set the connection's `retry_after` above the submission job's timeout, which is derived from
  the `http` settings: about 95 seconds with the defaults.
- Schedule `nav:resume` to pick up transactions whose job was lost, for example after a failed
  dispatch or a worker killed on its last attempt:

  ```php
  Schedule::command('nav:resume')->everyTenMinutes();
  ```
- Events fire once, after the database commit. Make listeners that must not miss an event
  queued (`ShouldQueue`), so a failing listener is retried on its own.

## Multiple taxpayers

```php
// config/nav-online-szamla.php
'tenants' => [
    'acme' => [
        'login' => env('ACME_NAV_USERNAME'),
        // password, tax_number, sign_key, exchange_key
    ],
],
```

```php
Nav::for('acme')->queryTaxpayer('87654321');
Nav::submit($invoice, tenant: 'acme');
```

To load credentials from anywhere else (for example an encrypted database column), implement
`IntrAgile\NavOnlineSzamla\Tenancy\CredentialsResolver` and set it as `credentials_resolver`,
or bind it in the container.

## Operations

| Operation | Method |
|---|---|
| tokenExchange | `Nav::tokenExchange()` |
| manageInvoice | `Nav::manageInvoice($submissions)`, or queued: `Nav::submit()` |
| manageAnnulment | `Nav::manageAnnulment($annulments)`, or queued: `Nav::annul()` |
| queryTransactionStatus | `Nav::queryTransactionStatus($id, returnOriginalRequest: false)` |
| queryTransactionList | `Nav::queryTransactionList($from, $to)`, all pages: `Nav::transactions()` |
| queryInvoiceData | `Nav::queryInvoiceData(InvoiceNumberQuery::outbound('...'))` |
| queryInvoiceCheck | `Nav::queryInvoiceCheck(...)` |
| queryInvoiceDigest | `Nav::queryInvoiceDigest(InvoiceDigestQuery::issuedBetween($from, $to))`, all pages: `Nav::invoiceDigests()` |
| queryInvoiceChainDigest | `Nav::queryInvoiceChainDigest(...)`, all pages: `Nav::invoiceChain()` |
| queryTaxpayer | `Nav::queryTaxpayer('12345678')` |

Inject `IntrAgile\NavOnlineSzamla\NavClient` to get the default taxpayer's client.

## Errors

All exceptions extend `NavException`.

| Exception | Meaning |
|---|---|
| `ConfigurationException` | missing or malformed settings; raised before any request |
| `XsdValidationException` | the document violates the official schema; nothing was sent |
| `InvoiceValidationException` | a blocking NAV business rule would reject the invoice |
| `NavApiException` | NAV answered with an error: `errorCode`, `isRetryable()`, `isAuthenticationError()` |
| `TransportException` | the request did not reach NAV or the response was lost |

Queries and `tokenExchange` are retried with a new request ID on transient errors. Requests are
never logged: they contain the password hash and the exchange token, and secrets are masked
in debug output.

## Testing

```php
use IntrAgile\NavOnlineSzamla\Facades\Nav;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;

Nav::fake()
    ->taxpayer('87654321', 'Vevő Kft.')
    ->rejectInvoice('INV-13', 'INVOICE_NUMBER_NOT_UNIQUE');

// ... run your code ...

Nav::assertInvoiceSubmitted('INV-2026-0001');
Nav::assertInvoiceSubmitted(fn (InvoiceData $invoice) => $invoice->invoiceMain->invoice?->invoiceHead->customerInfo?->customerName === 'Vevő Kft.');
Nav::assertSubmittedCount(1);
Nav::assertTaxpayerQueried('87654321');
```

The fake is an in-memory NAV. It keeps accepted invoices, so `queryInvoiceCheck`,
`queryInvoiceData`, `queryTransactionStatus` and `queryTransactionList` stay consistent with
what your code sent. It still validates outgoing documents against the XSDs. Queued
submissions run through the real jobs without delays.

| Configure | Assert |
|---|---|
| `taxpayer($taxNumber, $nameOrTaxpayerOrNull)` | `assertInvoiceSubmitted($numberOrClosure, $tenant)` |
| `rejectInvoice($number, $code)` | `assertInvoiceNotSubmitted(...)`, `assertNothingSubmitted()` |
| `warnInvoice($number, $code)` | `assertSubmittedCount($n)` |
| `failNext($operation, NavFake::apiError(...) \| NavFake::connectionFailure())` | `assertAnnulmentSubmitted(...)` |
| `respond($operation, fn (...$args) => ...)` | `assertTaxpayerQueried($taxNumber)` |
| `withTaxNumber($taxNumber, $tenant)` | `assertSent($operation, $closure)`, `assertNotSent()`, `assertSentCount()`, `assertNothingSent()` |

`Http::fake()` works as well, if you prefer to fake at the HTTP level.

## Artisan commands

| Command | Purpose |
|---|---|
| `nav:software-id [tax-number] [--write] [--force]` | generate the softwareId (once) |
| `nav:test-connection [--tenant=]` | verify credentials with a token exchange and a taxpayer query |
| `nav:status [id] [--tenant=]` | list open transactions, or show one (local ID or NAV transaction ID) with its live NAV status |
| `nav:resume [--idle=10]` | re-dispatch the job of open transactions idle for the given minutes; schedule it |

## Schemas and the generated model

The official XSDs are bundled in `resources/xsd`. The invoice model in `src/Invoice/Model` is
**generated** from them, so never edit it by hand. When NAV publishes new schemas:

```bash
git clone --depth 1 https://github.com/nav-gov-hu/Online-Invoice.git /tmp/nav
php bin/sync-xsd.php /tmp/nav          # update the bundled XSDs
php bin/generate-model.php             # regenerate the model
php bin/generate-model.php --check     # CI: fails when the model is stale
```

A weekly CI job reports when the bundled schemas drift from NAV's repository.

## Development

```bash
composer test        # PHPUnit
composer analyse     # PHPStan, max level
composer format      # Pint
```

The end-to-end suite runs every operation against the NAV test environment. It is opt-in and
needs a test technical user in a dotenv file (`NAV_USERNAME`, `NAV_PASSWORD`, `NAV_TAX_NUMBER`,
`NAV_SIGN_KEY`, `NAV_EXCHANGE_KEY`). It always targets the test environment.

```bash
NAV_E2E_ENV=/path/to/.env.test vendor/bin/phpunit --group e2e   # default: ~/.config/nav/.env.test
```

## License

MIT. See [LICENSE](LICENSE).

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu).
