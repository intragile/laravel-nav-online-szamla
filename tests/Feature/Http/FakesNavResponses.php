<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Feature\Http;

use DateTimeImmutable;
use Illuminate\Http\Client\Factory;
use IntrAgile\NavOnlineSzamla\Config\Credentials;
use IntrAgile\NavOnlineSzamla\Config\Software;
use IntrAgile\NavOnlineSzamla\Crypto\TokenDecoder;
use IntrAgile\NavOnlineSzamla\Http\Transport;
use IntrAgile\NavOnlineSzamla\NavClient;
use IntrAgile\NavOnlineSzamla\Support\UlidRequestIdGenerator;

trait FakesNavResponses
{
    private const URL = 'https://api-test.onlineszamla.nav.gov.hu/invoiceService/v3';

    private const EXCHANGE_KEY = '1234567890abcdef';

    private const SIGN_KEY = 'sign-key-0001';

    private function client(int $retries = 2): NavClient
    {
        return new NavClient(
            new Credentials('abcdefghij12345', 'secret', '12345678', self::SIGN_KEY, self::EXCHANGE_KEY),
            new Software('HU12345678-ABC1234', 'Test', 'ONLINE_SERVICE', '1.0', 'Dev Kft.', 'dev@example.com', 'HU'),
            new Transport(app(Factory::class), self::URL),
            new UlidRequestIdGenerator,
            retries: $retries,
            retryDelayMs: 0,
            clock: fn () => new DateTimeImmutable('2024-03-01T10:00:00.000Z'),
        );
    }

    /**
     * A response recorded from the NAV test environment (anonymised).
     */
    private function recorded(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/../../fixtures/nav-responses/'.$name.'.xml');
    }

    private function tokenResponse(): string
    {
        $encoded = TokenDecoder::encode('decoded-token-value', self::EXCHANGE_KEY);

        return $this->okResponse('TokenExchangeResponse', <<<XML
  <encodedExchangeToken>{$encoded}</encodedExchangeToken>
  <tokenValidityFrom>2024-03-01T10:00:00.000Z</tokenValidityFrom>
  <tokenValidityTo>2024-03-01T10:05:00.000Z</tokenValidityTo>
XML);
    }

    private function transactionResponse(string $root, string $transactionId): string
    {
        return $this->okResponse($root, "<transactionId>{$transactionId}</transactionId>");
    }

    private function okResponse(string $root, string $body): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<{$root} xmlns="http://schemas.nav.gov.hu/OSA/3.0/api" xmlns:common="http://schemas.nav.gov.hu/NTCA/1.0/common">
  <common:header><common:requestId>X</common:requestId><common:timestamp>2024-03-01T10:00:00.000Z</common:timestamp><common:requestVersion>3.0</common:requestVersion><common:headerVersion>1.0</common:headerVersion></common:header>
  <common:result><common:funcCode>OK</common:funcCode></common:result>
  <software><softwareId>HU12345678-ABC1234</softwareId><softwareName>Test</softwareName><softwareOperation>ONLINE_SERVICE</softwareOperation><softwareMainVersion>1.0</softwareMainVersion><softwareDevName>Dev</softwareDevName><softwareDevContact>d</softwareDevContact></software>
{$body}
</{$root}>
XML;
    }

    private function errorResponse(string $code, string $message = 'error'): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<GeneralErrorResponse xmlns="http://schemas.nav.gov.hu/OSA/3.0/api" xmlns:common="http://schemas.nav.gov.hu/NTCA/1.0/common">
  <common:header><common:requestId>X</common:requestId><common:timestamp>2024-03-01T10:00:00.000Z</common:timestamp><common:requestVersion>3.0</common:requestVersion></common:header>
  <common:result><common:funcCode>ERROR</common:funcCode><common:errorCode>{$code}</common:errorCode><common:message>{$message}</common:message></common:result>
  <technicalValidationMessages><validationResultCode>ERROR</validationResultCode><validationErrorCode>SCHEMA_VIOLATION</validationErrorCode><message>invalid element</message></technicalValidationMessages>
</GeneralErrorResponse>
XML;
    }
}
