# Laravel NAV Online Számla

Teljes, Laravel-natív kliens a **NAV Online Számla v3.0** interfészhez.

[English documentation → README.md](README.md)

- **Mind a 10 művelet** típusos kérés- és válaszobjektumokkal. Nyers XML-hez nem kell nyúlni.
- **Típusos számla-modell**, amelyet a hivatalos XSD-kből generálunk. Mellé jár egy **builder**,
  amely kiszámolja a tételösszegeket, az ÁFA-összesítőt és a végösszegeket. Tud
  CREATE/MODIFY/STORNO műveletet, egyszerűsített, gyűjtő-, devizás és előlegszámlát.
- **Ellenőrzés még beküldés előtt:** minden dokumentumot validálunk a csomagban lévő hivatalos
  XSD-k ellen, és lefuttatjuk a NAV blokkoló üzleti szabályait is, amennyiben az adatokból
  eldönthetők.
- **A Laravel HTTP kliensére épül**, így a `Http::fake()`, a Telescope és a Pulse is látja
  a NAV-forgalmat.
- **Queue-s beküldés tranzakciónaplóval.** A státusz-polling backoff-fal fut, és minden
  lépésről event megy ki. Timeout után a csomag **egyeztet, és nem küld vakon újra**, így
  egy számla sosem kerül kétszer a NAV-hoz.
- **Több adóalany (tenant)**, artisan parancsok, `Nav::fake()` assertion-ökkel.

## Követelmények

- PHP 8.3+ (`bcmath`, `dom`, `libxml`, `openssl`)
- Laravel 12 vagy 13

## Telepítés

```bash
composer require intragile/laravel-nav-online-szamla
php artisan vendor:publish --tag=nav-online-szamla-config
php artisan migrate
```

Hozz létre technikai felhasználót az Online Számla felületén (először a
[teszt-környezetben](https://onlineszamla-test.nav.gov.hu)), majd állítsd be:

```dotenv
NAV_ENVIRONMENT=test            # "production" csak tudatosan
NAV_USERNAME=...
NAV_PASSWORD=...
NAV_TAX_NUMBER=12345678         # bármilyen formátum; az első 8 számjegy számít
NAV_SIGN_KEY=...
NAV_EXCHANGE_KEY=...

NAV_SOFTWARE_ID=                # lásd lent
NAV_SOFTWARE_DEV_NAME="Cégem Kft."
NAV_SOFTWARE_DEV_CONTACT=dev@example.com
NAV_SOFTWARE_DEV_TAX_NUMBER=12345678
```

Minden kérés egy 18 karakteres `softwareId`-vel azonosítja a szoftvert. **Egyszer** generáld
le, és utána ne változtasd: ez azonosítja a szoftvert minden telepítésben.

```bash
php artisan nav:software-id --write    # beírja a NAV_SOFTWARE_ID-t a .env-be
php artisan nav:test-connection        # tokenkérés és adózó-lekérdezés a saját adatokkal
```

## Gyors kezdés

```php
use IntrAgile\NavOnlineSzamla\Facades\Nav;

$taxpayer = Nav::queryTaxpayer('87654321-2-13');   // ?Taxpayer
$taxpayer?->name;
$taxpayer?->headquarters()?->city;
```

Számla összeállítása és beküldése queue-n át:

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

$transaction = Nav::submit($invoice, subject: $order);   // NavTransaction, "pending" állapotban
```

A `Nav::submit()` eltárolja a pontos XML-t a tranzakciónaplóban, hozzáköti a saját modelledhez,
és elindítja a beküldő jobot. Az eredményről eventek tájékoztatnak (lásd:
[Queue-s beküldés](#queue-s-beküldés)).

Szinkron módon:

```php
$transactionId = Nav::manageInvoice($invoice->submission());
$status = Nav::queryTransactionStatus($transactionId);

if ($status->isFinished()) {
    $result = $status->results[0];
    $result->isAccepted();        // DONE
    $result->warnings();          // list<BusinessValidationMessage>
}
```

## Számla-builder

A builder minden összeget kiszámol: a tételek nettó, ÁFA- és bruttó összegét, devizás számlánál
a forintösszegeket, valamint az ÁFA-kulcsonkénti összesítőt és a végösszegeket. A kerekítés
„half away from zero” szabályt követ, mindenhol `bcmath` decimálisokkal, float nélkül.

| Igény | API |
|---|---|
| Tételtípus | `LineItem::product()`, `::service()`, `::other()` |
| Mennyiség / ár | `->quantity('1.5', UnitOfMeasure::Kilogram)`, `->unitPrice('400')`, vagy `->netAmount()`, `->grossAmount()` |
| ÁFA | `Vat::rate(27)`, `Vat::exempt('AAM', 'Alanyi adómentes')`, `Vat::outOfScope(...)`, `Vat::domesticReverseCharge()`, `Vat::marginScheme(...)`, `Vat::noCharge()`, `Vat::content(...)` (egyszerűsített számla) |
| Engedmény | `->discount('Árengedmény', rate: '0.1')` |
| Előleg | `->asAdvance()`, `->settlesAdvance('ELO-1', '2026-10-01', '392.17')` |
| Vevő | `Party::domesticCustomer()`, `::euCustomer()`, `::thirdStateCustomer()`, `::otherCustomer()`, `::privatePerson()` |
| Számlafajta | `->simplified()`, `->aggregate()`, `->currency('EUR', '392.17')`, `->completeElectronicInvoice()` |
| Módosítás | `InvoiceBuilder::modify('INV-1-M', 'INV-1', modificationIndex: 1)`, `BatchModification::of(...)` |

A `build()` validálás után a típusos `InvoiceData` modellt adja vissza, a `submission()` pedig
a beküldendő `InvoiceSubmission`-t.

### Sztornó

A `stornoOf()` az eredeti számla minden tételét pontosan ellentett összeggel fordítja meg, a
felek és a számlaadatok változatlanok maradnak. Az eredetit a legegyszerűbb a NAV-tól lekérni:

```php
use IntrAgile\NavOnlineSzamla\Requests\InvoiceNumberQuery;

$original = Nav::queryInvoiceData(InvoiceNumberQuery::outbound('INV-2026-0001'))?->invoice();

$storno = InvoiceBuilder::stornoOf($original, 'INV-2026-0001-S', modificationIndex: 1, issueDate: '2026-11-05');
Nav::submit($storno);
```

## Ellenőrzés beküldés előtt

A NAV sok számlát csak a feldolgozás után, percekkel később utasít el ABORTED státusszal. Az
`InvoiceValidator` lefuttatja azokat a blokkoló szabályokat (a specifikáció 3.3.2. fejezete),
amelyek a beküldött adatból eldönthetők. Így a hibák többsége még a küldés előtt kivételként
jelentkezik:

```php
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceValidationException;

try {
    $invoice->build();
} catch (InvoiceValidationException $e) {
    $e->codes();          // ['INVOICE_ISSUE_DATE_LATE']
    $e->violations;       // list<Violation>: kód, üzenet, útvonal
}
```

Néhány szabály olyan tényen múlik, ami a számlából nem látszik. Ha biztosan tudod, hogy a NAV
elfogadja az esetedet, a szabály kihagyható egy számlánál vagy az egész alkalmazásban:

```php
use IntrAgile\NavOnlineSzamla\Invoice\Validation\InvoiceValidator;

$invoice->validateWith((new InvoiceValidator)->skip('MISSING_CUSTOMER_DOMESTIC_TAXNUMBER'));
```

```php
// config/nav-online-szamla.php
'validation' => ['enabled' => true, 'skip' => ['MISSING_CUSTOMER_DOMESTIC_TAXNUMBER']],
```

A queue job a **bejelentkezett** technikai felhasználó adószámával is ellenőriz, így a
`SUPPLIER_TAX_NUMBER_MISMATCH` hibát is elkapja, mielőtt a NAV jelezné.

## Queue-s beküldés

```
Nav::submit() ─► pending ─► sending ─► processing ─► finished   (minden tétel DONE vagy ABORTED)
                    │          │            └─────► abandoned  (elfogyott a polling)
                    │          ├──► rejected    (a NAV magát a kérést utasította el)
                    │          └──► unconfirmed ─► egyeztetés ─► processing | pending | abandoned
                    └──► rejected (kliensoldali validáció)
```

A beküldés előtt a job atomikusan lefoglalja a tranzakciót, így duplikált vagy újrafutó job sem
küldheti el kétszer. A `manageInvoice` automatikusan soha nem ismétlődik.

**Egyeztetés vak újraküldés helyett.** Timeout vagy elveszett válasz után nem tudni, mit tett a
NAV: lehet, hogy befogadta a számlát. A tranzakció `unconfirmed` állapotba kerül, és
`reconciliation.delay` másodperc múlva a csomag:

1. lekéri a küldési időablak tranzakcióit (`queryTransactionList`),
2. a jelöltek eredeti kérését összeveti a naplózott XML-lel,
3. egyezés esetén a megtalált tranzakcióazonosítóval folytatja a pollingot,
4. ha nincs egyezés, és a `queryInvoiceCheck` sem ismeri egyik számlát sem, akkor újraküld.
   Ez az egyetlen eset, amikor bármi újra elmegy.

| Event | Mikor |
|---|---|
| `TransactionSubmitted` | a NAV kiadta a tranzakcióazonosítót |
| `InvoiceAccepted` / `InvoiceRejected` | egy tétel DONE / ABORTED státuszt kapott (a NAV üzeneteivel) |
| `TransactionFinished` | minden tétel feldolgozása befejeződött |
| `TransactionRejected` | soha nem került befogadásra: kliensoldali validáció vagy NAV-hiba; lásd `error_code` |
| `TransactionUnconfirmed` | ismeretlen kimenet; egyeztetés ütemezve |
| `TransactionReconciled` | az egyeztetés megtalálta a tranzakciót (`found: true`), vagy újraküldést ütemezett |
| `TransactionAbandoned` | az automatikus kezelés feladta; kézi beavatkozás kell |

```php
use IntrAgile\NavOnlineSzamla\Events\InvoiceAccepted;

Event::listen(function (InvoiceAccepted $event) {
    $event->item->subject?->update(['nav_status' => 'reported']);
    $event->result->warnings();
});
```

A queue kapcsolatát és nevét, a polling backoffját (`polling.delays`, az utolsó érték ismétlődik)
és az egyeztetés korlátait a `config/nav-online-szamla.php` állítja. A `sync` driver is működik,
de a várakozásokat a futó folyamatban tölti ki. Élesben valódi queue-t használj.

## Több adóalany

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

Ha a hitelesítő adatok máshonnan jönnek (például titkosított adatbázismezőből), implementáld az
`IntrAgile\NavOnlineSzamla\Tenancy\CredentialsResolver` interfészt, és add meg
`credentials_resolver`-ként, vagy kösd be a containerbe.

## Műveletek

| Művelet | Metódus |
|---|---|
| tokenExchange | `Nav::tokenExchange()` |
| manageInvoice | `Nav::manageInvoice($submissions)`, vagy queue-n: `Nav::submit()` |
| manageAnnulment | `Nav::manageAnnulment($annulments)`, vagy queue-n: `Nav::annul()` |
| queryTransactionStatus | `Nav::queryTransactionStatus($id, returnOriginalRequest: false)` |
| queryTransactionList | `Nav::queryTransactionList($from, $to)`, minden oldal: `Nav::transactions()` |
| queryInvoiceData | `Nav::queryInvoiceData(InvoiceNumberQuery::outbound('...'))` |
| queryInvoiceCheck | `Nav::queryInvoiceCheck(...)` |
| queryInvoiceDigest | `Nav::queryInvoiceDigest(InvoiceDigestQuery::issuedBetween($from, $to))`, minden oldal: `Nav::invoiceDigests()` |
| queryInvoiceChainDigest | `Nav::queryInvoiceChainDigest(...)`, minden oldal: `Nav::invoiceChain()` |
| queryTaxpayer | `Nav::queryTaxpayer('12345678')` |

Az alapértelmezett adózó kliensét az `IntrAgile\NavOnlineSzamla\NavClient` injektálásával is
megkapod.

## Hibák

Minden kivétel a `NavException` leszármazottja.

| Kivétel | Jelentés |
|---|---|
| `ConfigurationException` | hiányzó vagy hibás beállítás; még kérés előtt jelentkezik |
| `XsdValidationException` | a dokumentum sérti a hivatalos sémát; semmi nem ment el |
| `InvoiceValidationException` | egy blokkoló NAV üzleti szabály elutasítaná a számlát |
| `NavApiException` | a NAV hibával válaszolt: `errorCode`, `isRetryable()`, `isAuthenticationError()` |
| `TransportException` | a kérés nem jutott el a NAV-hoz, vagy elveszett a válasz |

A lekérdezéseket és a `tokenExchange`-et átmeneti hiba esetén új kérésazonosítóval
megismételjük. Kérést soha nem naplózunk, mert jelszó-hash-t és tokent tartalmaz. A titkok a
debug-kimenetben maszkolva jelennek meg.

## Tesztelés

```php
use IntrAgile\NavOnlineSzamla\Facades\Nav;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;

Nav::fake()
    ->taxpayer('87654321', 'Vevő Kft.')
    ->rejectInvoice('INV-13', 'INVOICE_NUMBER_NOT_UNIQUE');

// ... a tesztelt kód ...

Nav::assertInvoiceSubmitted('INV-2026-0001');
Nav::assertInvoiceSubmitted(fn (InvoiceData $invoice) => $invoice->invoiceMain->invoice?->invoiceHead->customerInfo?->customerName === 'Vevő Kft.');
Nav::assertSubmittedCount(1);
Nav::assertTaxpayerQueried('87654321');
```

A fake egy memóriában futó NAV. Megőrzi a befogadott számlákat, így a `queryInvoiceCheck`, a
`queryInvoiceData`, a `queryTransactionStatus` és a `queryTransactionList` összhangban marad
azzal, amit a kódod beküldött. A kimenő dokumentumokat továbbra is validálja az XSD-k ellen. A
queue-s beküldés a valódi jobokon fut végig, várakozás nélkül.

| Beállítás | Assertion |
|---|---|
| `taxpayer($taxNumber, $névVagyTaxpayerVagyNull)` | `assertInvoiceSubmitted($számVagyClosure, $tenant)` |
| `rejectInvoice($number, $code)` | `assertInvoiceNotSubmitted(...)`, `assertNothingSubmitted()` |
| `warnInvoice($number, $code)` | `assertSubmittedCount($n)` |
| `failNext($operation, NavFake::apiError(...) \| NavFake::connectionFailure())` | `assertAnnulmentSubmitted(...)` |
| `respond($operation, fn (...$args) => ...)` | `assertTaxpayerQueried($taxNumber)` |
| `withTaxNumber($taxNumber, $tenant)` | `assertSent($operation, $closure)`, `assertNotSent()`, `assertSentCount()`, `assertNothingSent()` |

HTTP-szinten a `Http::fake()` is használható.

## Artisan parancsok

| Parancs | Mire való |
|---|---|
| `nav:software-id [tax-number] [--write] [--force]` | a softwareId legenerálása (egyszer) |
| `nav:test-connection [--tenant=]` | a hitelesítő adatok ellenőrzése tokenkéréssel és adózó-lekérdezéssel |
| `nav:status [id] [--tenant=]` | a nyitott tranzakciók listája, vagy egy tranzakció (helyi vagy NAV-azonosító) élő NAV-státusza |

## Sémák és a generált modell

A hivatalos XSD-k a `resources/xsd` mappában vannak. Az `src/Invoice/Model` alatti modell ezekből
**generált** kód, kézzel soha ne szerkeszd. Ha a NAV új sémát ad ki:

```bash
git clone --depth 1 https://github.com/nav-gov-hu/Online-Invoice.git /tmp/nav
php bin/sync-xsd.php /tmp/nav          # a csomag XSD-inek frissítése
php bin/generate-model.php             # a modell újragenerálása
php bin/generate-model.php --check     # CI: hibát jelez, ha a modell elavult
```

Egy heti CI-futás jelzi, ha a csomag sémái eltérnek a NAV repójától.

## Fejlesztés

```bash
composer test        # PHPUnit
composer analyse     # PHPStan, max szint
composer format      # Pint
```

Az end-to-end suite minden műveletet lefuttat a NAV teszt-környezete ellen. Csak külön kérésre
fut, és egy teszt technikai felhasználót vár dotenv fájlban (`NAV_USERNAME`, `NAV_PASSWORD`,
`NAV_TAX_NUMBER`, `NAV_SIGN_KEY`, `NAV_EXCHANGE_KEY`). Mindig a teszt-környezetet hívja.

```bash
NAV_E2E_ENV=/útvonal/.env.test vendor/bin/phpunit --group e2e   # alapértelmezés: ~/.config/nav/.env.test
```

## Licenc

MIT. Lásd: [LICENSE](LICENSE).
