<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Feature\Http;

use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use IntrAgile\NavOnlineSzamla\Crypto\Signer;
use IntrAgile\NavOnlineSzamla\Exceptions\NavApiException;
use IntrAgile\NavOnlineSzamla\Exceptions\TransportException;
use IntrAgile\NavOnlineSzamla\Tests\TestCase;
use IntrAgile\NavOnlineSzamla\Xml\Schema;
use IntrAgile\NavOnlineSzamla\Xml\XsdValidator;

final class NavClientTest extends TestCase
{
    use FakesNavResponses;

    public function test_token_exchange_sends_a_schema_valid_signed_request_and_decodes_the_token(): void
    {
        Http::fake([self::URL.'/tokenExchange' => Http::response($this->tokenResponse())]);

        $token = $this->client()->tokenExchange();

        $this->assertSame('decoded-token-value', $token->token);
        $this->assertSame('2024-03-01T10:05:00+00:00', $token->validTo->format(DATE_ATOM));

        Http::assertSent(function (Request $request): bool {
            XsdValidator::validate($request->body(), Schema::Api);
            $xml = simplexml_load_string($request->body());
            $this->assertNotFalse($xml);
            $common = $xml->children('http://schemas.nav.gov.hu/NTCA/1.0/common');
            $requestId = (string) $common->header->requestId;

            $this->assertSame('2024-03-01T10:00:00.000Z', (string) $common->header->timestamp);
            $this->assertSame(Signer::passwordHash('secret'), (string) $common->user->passwordHash);
            $this->assertSame(
                Signer::requestSignature($requestId, new DateTimeImmutable('2024-03-01T10:00:00Z'), 'sign-key-0001'),
                (string) $common->user->requestSignature,
            );
            $this->assertSame('application/xml', $request->header('Content-Type')[0]);

            return true;
        });
    }

    public function test_nav_error_is_mapped_to_a_typed_exception(): void
    {
        Http::fake(['*' => Http::response($this->errorResponse('INVALID_SECURITY_USER', 'Helytelen autentikációs kód!'), 401)]);

        try {
            $this->client()->tokenExchange();
            $this->fail('Expected an exception.');
        } catch (NavApiException $e) {
            $this->assertSame('INVALID_SECURITY_USER', $e->errorCode);
            $this->assertSame(401, $e->httpStatus);
            $this->assertTrue($e->isAuthenticationError());
            $this->assertFalse($e->isRetryable());
            $this->assertSame('SCHEMA_VIOLATION', $e->technicalValidationMessages[0]->errorCode);
            $this->assertStringContainsString('Helytelen autentikációs kód!', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_retryable_errors_are_retried_with_a_fresh_request_id(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->errorResponse('OPERATION_FAILED'), 500)
            ->push('Generic exception occured!', 500)
            ->push($this->tokenResponse())]);

        $this->assertSame('decoded-token-value', $this->client()->tokenExchange()->token);

        $ids = [];
        Http::assertSent(function (Request $request) use (&$ids): bool {
            preg_match('#<common:requestId>([^<]+)</common:requestId>#', $request->body(), $m);
            $ids[] = $m[1];

            return true;
        });
        $this->assertCount(3, $ids);
        $this->assertCount(3, array_unique($ids));
    }

    public function test_retries_are_bounded(): void
    {
        Http::fake(['*' => Http::response($this->errorResponse('OPERATION_FAILED'), 500)]);

        try {
            $this->client(retries: 2)->tokenExchange();
            $this->fail('Expected an exception.');
        } catch (NavApiException $e) {
            $this->assertSame('OPERATION_FAILED', $e->errorCode);
        }
        Http::assertSentCount(3);
    }

    public function test_connection_failure_becomes_transport_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(TransportException::class);
        $this->client(retries: 0)->tokenExchange();
    }

    public function test_non_idempotent_calls_are_never_retried(): void
    {
        Http::fake(['*' => Http::response($this->errorResponse('OPERATION_FAILED'), 500)]);

        try {
            $this->client()->call('manageInvoice', 'TokenExchangeRequest', idempotent: false);
        } catch (NavApiException) {
        }
        Http::assertSentCount(1);
    }

    public function test_credentials_never_leak_through_debug_output(): void
    {
        $dump = print_r($this->client()->credentials, true);

        $this->assertStringNotContainsString('secret', $dump);
        $this->assertStringNotContainsString('sign-key-0001', $dump);
        $this->assertStringNotContainsString(self::EXCHANGE_KEY, $dump);
    }
}
