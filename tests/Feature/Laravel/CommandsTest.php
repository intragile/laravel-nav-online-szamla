<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Feature\Laravel;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use IntrAgile\NavOnlineSzamla\Enums\TransactionState;
use IntrAgile\NavOnlineSzamla\Facades\Nav;
use IntrAgile\NavOnlineSzamla\Tests\Support\InvoiceFactory;

final class CommandsTest extends IntegrationTestCase
{
    private ?string $envFile = null;

    protected function tearDown(): void
    {
        if ($this->envFile !== null && is_file($this->envFile)) {
            unlink($this->envFile);
        }
        parent::tearDown();
    }

    private function useEnvFile(string $contents): string
    {
        $dir = sys_get_temp_dir().'/nav-env-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $this->envFile = $dir.'/.env';
        file_put_contents($this->envFile, $contents);
        $this->app->useEnvironmentPath($dir);

        return $this->envFile;
    }

    public function test_software_id_is_generated_from_the_given_tax_number(): void
    {
        $this->artisan('nav:software-id', ['tax-number' => '12345678-2-41'])
            ->expectsOutputToContain('HU12345678-')
            ->assertSuccessful();
    }

    public function test_software_id_falls_back_to_the_configured_tax_number(): void
    {
        $this->artisan('nav:software-id')->expectsOutputToContain('HU12345678-')->assertSuccessful();
    }

    public function test_software_id_requires_a_tax_number(): void
    {
        config(['nav-online-szamla.credentials.tax_number' => null]);

        $this->artisan('nav:software-id')->assertFailed();
    }

    public function test_software_id_is_written_to_the_environment_file(): void
    {
        $path = $this->useEnvFile("APP_NAME=Test\nNAV_SOFTWARE_ID=\n");

        $this->artisan('nav:software-id', ['tax-number' => '12345678', '--write' => true])->assertSuccessful();

        $this->assertMatchesRegularExpression('/^NAV_SOFTWARE_ID=HU12345678-[0-9A-Z]{7}$/m', (string) file_get_contents($path));
        $this->assertStringContainsString('APP_NAME=Test', (string) file_get_contents($path));
    }

    public function test_software_id_is_appended_when_missing(): void
    {
        $path = $this->useEnvFile("APP_NAME=Test\n");

        $this->artisan('nav:software-id', ['tax-number' => '12345678', '--write' => true])->assertSuccessful();

        $this->assertMatchesRegularExpression('/^NAV_SOFTWARE_ID=HU12345678-[0-9A-Z]{7}$/m', (string) file_get_contents($path));
    }

    public function test_an_existing_software_id_is_kept_without_force(): void
    {
        $path = $this->useEnvFile("NAV_SOFTWARE_ID=HU12345678-KEEPME1\n");

        $this->artisan('nav:software-id', ['tax-number' => '12345678', '--write' => true])->assertFailed();
        $this->assertStringContainsString('HU12345678-KEEPME1', (string) file_get_contents($path));

        $this->artisan('nav:software-id', ['tax-number' => '12345678', '--write' => true, '--force' => true])->assertSuccessful();
        $this->assertStringNotContainsString('HU12345678-KEEPME1', (string) file_get_contents($path));
    }

    public function test_connection_check_succeeds_with_valid_credentials(): void
    {
        Http::fake([
            self::URL.'/tokenExchange' => Http::response($this->tokenResponse()),
            self::URL.'/queryTaxpayer' => Http::response((string) file_get_contents(__DIR__.'/../../fixtures/nav-responses/queryTaxpayer.xml')),
        ]);

        $this->artisan('nav:test-connection')
            ->expectsOutputToContain('12345678')
            ->expectsOutputToContain('The NAV connection works.')
            ->assertSuccessful();
    }

    public function test_connection_check_reports_rejected_credentials(): void
    {
        Http::fake([self::URL.'/tokenExchange' => Http::response($this->errorResponse('INVALID_SECURITY_USER', 'Helytelen authentikációs adatok!'), 401)]);

        $this->artisan('nav:test-connection', ['--tenant' => 'acme'])
            ->expectsOutputToContain('INVALID_SECURITY_USER')
            ->expectsOutputToContain('The credentials were rejected.')
            ->assertFailed();
    }

    public function test_connection_check_reports_configuration_errors(): void
    {
        $this->artisan('nav:test-connection', ['--tenant' => 'missing'])
            ->expectsOutputToContain('Unknown NAV tenant: missing')
            ->assertFailed();
    }

    public function test_status_lists_open_transactions(): void
    {
        Bus::fake();
        $open = Nav::submit(InvoiceFactory::domestic('INV-OPEN')->line(InvoiceFactory::product()));
        $done = Nav::submit(InvoiceFactory::domestic('INV-DONE')->line(InvoiceFactory::product()));
        $done->transition(TransactionState::Pending, TransactionState::Finished);

        $this->artisan('nav:status')
            ->expectsTable(
                ['ID', 'Kind', 'Tenant', 'State', 'NAV transaction', 'Documents', 'Sent at', 'Error'],
                [[$open->id, 'invoice', '-', 'pending', '-', 1, '-', '']],
            )
            ->assertSuccessful();
    }

    public function test_status_without_open_transactions(): void
    {
        $this->artisan('nav:status')->expectsOutputToContain('no open NAV transactions')->assertSuccessful();
    }

    public function test_status_shows_a_local_transaction_with_its_live_nav_status(): void
    {
        Bus::fake();
        $transaction = Nav::submit(InvoiceFactory::domestic('INV-7')->line(InvoiceFactory::product()));
        $transaction->transition(TransactionState::Pending, TransactionState::Processing, ['transaction_id' => 'TX7']);
        Http::fake([self::URL.'/queryTransactionStatus' => Http::response($this->statusResponse([['ABORTED']]))]);

        $this->artisan('nav:status', ['transaction' => (string) $transaction->id])
            ->expectsOutputToContain('TX7')
            ->expectsOutputToContain('INV-7')
            ->expectsOutputToContain('INVOICE_NUMBER_NOT_UNIQUE')
            ->assertSuccessful();
    }

    public function test_status_queries_a_nav_transaction_id_directly(): void
    {
        Http::fake([self::URL.'/queryTransactionStatus' => Http::response($this->statusResponse([['DONE']]))]);

        $this->artisan('nav:status', ['transaction' => 'TXREMOTE01'])
            ->expectsOutputToContain('finished')
            ->assertSuccessful();
    }

    public function test_status_of_an_unknown_local_id_fails(): void
    {
        $this->artisan('nav:status', ['transaction' => '999'])->assertFailed();
    }
}
