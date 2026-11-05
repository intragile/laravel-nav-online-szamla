<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\E2E;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceAppearance;
use IntrAgile\NavOnlineSzamla\Enums\PaymentMethod;
use IntrAgile\NavOnlineSzamla\Facades\Nav;
use IntrAgile\NavOnlineSzamla\Invoice\InvoiceBuilder;
use IntrAgile\NavOnlineSzamla\Invoice\Model\CustomerInfo;
use IntrAgile\NavOnlineSzamla\Invoice\Model\SupplierInfo;
use IntrAgile\NavOnlineSzamla\Invoice\Party;
use IntrAgile\NavOnlineSzamla\Invoice\Validation\InvoiceValidator;
use IntrAgile\NavOnlineSzamla\Responses\ProcessingResult;
use IntrAgile\NavOnlineSzamla\Tests\TestCase;

/**
 * Runs against the NAV test environment with real credentials.
 *
 * Opt-in: vendor/bin/phpunit --group e2e (concrete test classes carry the group). Credentials are read from the dotenv file named
 * by NAV_E2E_ENV (default ~/.config/nav/.env.test) with NAV_USERNAME, NAV_PASSWORD,
 * NAV_TAX_NUMBER, NAV_SIGN_KEY and NAV_EXCHANGE_KEY. The tests are skipped without it. The
 * environment is always forced to "test", so nothing can reach production.
 */
abstract class E2ETestCase extends TestCase
{
    use RefreshDatabase;

    private const TEST_URL = 'https://api-test.onlineszamla.nav.gov.hu/invoiceService/v3';

    /** @var array<string, string>|null */
    private static ?array $env = null;

    private static ?SupplierInfo $supplier = null;

    protected function setUp(): void
    {
        if (self::credentials() === null) {
            $this->markTestSkipped('No NAV test credentials: set NAV_E2E_ENV or create ~/.config/nav/.env.test.');
        }

        parent::setUp();
    }

    protected function defineEnvironment($app): void
    {
        $env = self::credentials() ?? [];

        $app['config']->set('database.default', 'testing');
        $app['config']->set('nav-online-szamla.environment', 'test');
        $app['config']->set('nav-online-szamla.urls', ['test' => self::TEST_URL]);
        $app['config']->set('nav-online-szamla.credentials', [
            'login' => $env['NAV_USERNAME'] ?? '',
            'password' => $env['NAV_PASSWORD'] ?? '',
            'tax_number' => $env['NAV_TAX_NUMBER'] ?? '',
            'sign_key' => $env['NAV_SIGN_KEY'] ?? '',
            'exchange_key' => $env['NAV_EXCHANGE_KEY'] ?? '',
        ]);
        $app['config']->set('nav-online-szamla.software', [
            'id' => 'HU'.substr(preg_replace('/\D/', '', $env['NAV_TAX_NUMBER'] ?? '') ?? '', 0, 8).'-E2ETEST',
            'name' => 'IntrAgile NAV E2E',
            'operation' => 'ONLINE_SERVICE',
            'main_version' => '1.0',
            'dev_name' => 'IntrAgile Kft.',
            'dev_contact' => 'info@intragile.hu',
            'dev_country_code' => 'HU',
        ]);
        $app['config']->set('nav-online-szamla.http.timeout', 20);
    }

    /**
     * @return array<string, string>|null
     */
    private static function credentials(): ?array
    {
        if (self::$env !== null) {
            return self::$env;
        }

        $path = getenv('NAV_E2E_ENV') ?: rtrim((string) getenv('HOME'), '/').'/.config/nav/.env.test';
        if (! is_file($path)) {
            return null;
        }

        $env = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match('/^\s*([A-Z_]+)\s*=\s*"?(.*?)"?\s*$/', $line, $m) === 1) {
                $env[$m[1]] = $m[2];
            }
        }

        foreach (['NAV_USERNAME', 'NAV_PASSWORD', 'NAV_TAX_NUMBER', 'NAV_SIGN_KEY', 'NAV_EXCHANGE_KEY'] as $key) {
            if (($env[$key] ?? '') === '') {
                return null;
            }
        }

        return self::$env = $env;
    }

    /**
     * An invoice number that is unique across runs.
     */
    protected static function invoiceNumber(string $suffix = ''): string
    {
        return 'E2E/'.date('Ymd-His').'-'.substr(bin2hex(random_bytes(3)), 0, 5).$suffix;
    }

    protected function supplier(): SupplierInfo
    {
        if (self::$supplier !== null) {
            return self::$supplier;
        }

        $taxNumber = Nav::client()->credentials->taxNumber;
        $self = Nav::queryTaxpayer($taxNumber);

        return self::$supplier = Party::supplier(
            $taxNumber.'-'.($self->vatCode ?? '2').'-'.($self->countyCode ?? '41'),
            'E2E Teszt Eladó Kft.',
            Party::address('HU', '1011', 'Budapest', 'Fő utca 1.'),
            '11111111-22222222-33333333',
        );
    }

    protected function customer(): CustomerInfo
    {
        return Party::domesticCustomer('99887764-2-02', 'Beszerző Kft', Party::address('HU', '7600', 'Pécs', 'Északi sugárút 123.'));
    }

    protected function invoice(string $number): InvoiceBuilder
    {
        return InvoiceBuilder::create($number)
            ->issueDate(new DateTimeImmutable('today'))
            ->appearance(InvoiceAppearance::Electronic)
            ->payment(PaymentMethod::Transfer, new DateTimeImmutable('+15 days'))
            ->supplier($this->supplier())
            ->customer($this->customer());
    }

    /**
     * Send an invoice synchronously and wait until NAV has processed it.
     */
    protected function submitAndWait(InvoiceBuilder $builder): ProcessingResult
    {
        $submission = $builder->submission();
        $this->assertSame([], array_map(
            static fn ($v) => $v->code,
            (new InvoiceValidator)->validateSubmission($submission, null, Nav::client()->credentials->taxNumber),
        ));

        return $this->waitFor(Nav::manageInvoice($submission));
    }

    protected function waitFor(string $transactionId, bool $returnOriginalRequest = false): ProcessingResult
    {
        for ($i = 0; $i < 30; $i++) {
            $status = Nav::queryTransactionStatus($transactionId, $returnOriginalRequest);
            if ($status->isFinished()) {
                $this->assertNotNull($status->results[0] ?? null);

                return $status->results[0];
            }
            sleep(2);
        }

        $this->fail("NAV did not finish transaction {$transactionId} within a minute.");
    }

    protected function assertAccepted(ProcessingResult $result): void
    {
        $this->assertTrue($result->isAccepted(), 'NAV rejected the invoice: '.implode(', ', array_map(
            static fn ($m) => $m->errorCode.' '.$m->message,
            $result->errors(),
        )));
    }
}
