<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Feature\Laravel;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use IntrAgile\NavOnlineSzamla\Crypto\TokenDecoder;
use IntrAgile\NavOnlineSzamla\Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    protected const URL = 'https://api-test.onlineszamla.nav.gov.hu/invoiceService/v3';

    protected const LOGIN = 'abcdefghij12345';

    protected const EXCHANGE_KEY = '1234567890abcdef';

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('queue.default', 'null');
        $app['config']->set('nav-online-szamla.credentials', [
            'login' => self::LOGIN,
            'password' => 'secret',
            'tax_number' => '12345678-2-41',
            'sign_key' => 'sign-key-0001',
            'exchange_key' => self::EXCHANGE_KEY,
        ]);
        $app['config']->set('nav-online-szamla.tenants.acme', [
            'login' => 'acmeuser12345',
            'password' => 'secret',
            'tax_number' => '87654321',
            'sign_key' => 'sign-key-0002',
            'exchange_key' => 'fedcba0987654321',
        ]);
        $app['config']->set('nav-online-szamla.software', [
            'id' => 'HU12345678-ABC1234',
            'name' => 'Test',
            'operation' => 'ONLINE_SERVICE',
            'main_version' => '1.0',
            'dev_name' => 'Dev Kft.',
            'dev_contact' => 'dev@example.com',
            'dev_country_code' => 'HU',
        ]);
        $app['config']->set('nav-online-szamla.http.retries', 0);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function tokenResponse(): string
    {
        $encoded = TokenDecoder::encode('decoded-token-value', self::EXCHANGE_KEY);

        return $this->okResponse('TokenExchangeResponse', <<<XML
  <encodedExchangeToken>{$encoded}</encodedExchangeToken>
  <tokenValidityFrom>2026-11-05T11:00:00.000Z</tokenValidityFrom>
  <tokenValidityTo>2026-11-05T11:05:00.000Z</tokenValidityTo>
XML);
    }

    protected function manageResponse(string $transactionId, string $root = 'ManageInvoiceResponse'): string
    {
        return $this->okResponse($root, "<transactionId>{$transactionId}</transactionId>");
    }

    /**
     * @param  list<array{0: string, 1?: string|null}>  $results  Invoice status and the optional original request XML per index.
     */
    protected function statusResponse(array $results): string
    {
        $body = '';
        foreach ($results as $i => $result) {
            $original = isset($result[1]) ? '<originalRequest>'.base64_encode($result[1]).'</originalRequest>' : '';
            $body .= sprintf(
                '<processingResult><index>%d</index><invoiceStatus>%s</invoiceStatus>%s<compressedContentIndicator>false</compressedContentIndicator>%s</processingResult>',
                $i + 1,
                $result[0],
                $result[0] === 'ABORTED' ? '<businessValidationMessages><validationResultCode>ERROR</validationResultCode><validationErrorCode>INVOICE_NUMBER_NOT_UNIQUE</validationErrorCode><message>duplicate</message></businessValidationMessages>' : '',
                $original,
            );
        }

        return $this->okResponse('QueryTransactionStatusResponse', "<processingResults>{$body}<originalRequestVersion>3.0</originalRequestVersion></processingResults>");
    }

    /**
     * @param  list<array{id: string, login?: string, items?: int, annulment?: bool, at?: string}>  $transactions
     */
    protected function transactionListResponse(array $transactions): string
    {
        $body = '';
        foreach ($transactions as $t) {
            $body .= sprintf(
                '<transaction><insDate>%s</insDate><insCusUser>%s</insCusUser><source>MGM</source><transactionId>%s</transactionId><requestStatus>RECEIVED</requestStatus><technicalAnnulment>%s</technicalAnnulment><originalRequestVersion>3.0</originalRequestVersion><itemCount>%d</itemCount></transaction>',
                $t['at'] ?? '2026-11-05T11:00:01Z',
                $t['login'] ?? self::LOGIN,
                $t['id'],
                ($t['annulment'] ?? false) ? 'true' : 'false',
                $t['items'] ?? 1,
            );
        }

        return $this->okResponse('QueryTransactionListResponse', "<transactionListResult><currentPage>1</currentPage><availablePage>1</availablePage>{$body}</transactionListResult>");
    }

    protected function emptyTransactionListResponse(): string
    {
        return $this->okResponse('QueryTransactionListResponse', '<transactionListResult><currentPage>1</currentPage><availablePage>1</availablePage></transactionListResult>');
    }

    protected function invoiceCheckResponse(bool $exists): string
    {
        return $this->okResponse('QueryInvoiceCheckResponse', '<invoiceCheckResult>'.($exists ? 'true' : 'false').'</invoiceCheckResult>');
    }

    protected function okResponse(string $root, string $body): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<{$root} xmlns="http://schemas.nav.gov.hu/OSA/3.0/api" xmlns:common="http://schemas.nav.gov.hu/NTCA/1.0/common">
  <common:header><common:requestId>X</common:requestId><common:timestamp>2026-11-05T11:00:00.000Z</common:timestamp><common:requestVersion>3.0</common:requestVersion><common:headerVersion>1.0</common:headerVersion></common:header>
  <common:result><common:funcCode>OK</common:funcCode></common:result>
  <software><softwareId>HU12345678-ABC1234</softwareId><softwareName>Test</softwareName><softwareOperation>ONLINE_SERVICE</softwareOperation><softwareMainVersion>1.0</softwareMainVersion><softwareDevName>Dev</softwareDevName><softwareDevContact>d</softwareDevContact></software>
{$body}
</{$root}>
XML;
    }

    protected function errorResponse(string $code, string $message = 'error'): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<GeneralErrorResponse xmlns="http://schemas.nav.gov.hu/OSA/3.0/api" xmlns:common="http://schemas.nav.gov.hu/NTCA/1.0/common">
  <common:header><common:requestId>X</common:requestId><common:timestamp>2026-11-05T11:00:00.000Z</common:timestamp><common:requestVersion>3.0</common:requestVersion></common:header>
  <common:result><common:funcCode>ERROR</common:funcCode><common:errorCode>{$code}</common:errorCode><common:message>{$message}</common:message></common:result>
</GeneralErrorResponse>
XML;
    }
}
