<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Feature\Http;

use DateTimeImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use IntrAgile\NavOnlineSzamla\Crypto\Signer;
use IntrAgile\NavOnlineSzamla\Enums\AnnulmentCode;
use IntrAgile\NavOnlineSzamla\Enums\AnnulmentVerificationStatus;
use IntrAgile\NavOnlineSzamla\Enums\BusinessResultCode;
use IntrAgile\NavOnlineSzamla\Enums\Incorporation;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceOperation;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceStatus;
use IntrAgile\NavOnlineSzamla\Enums\QueryOperator;
use IntrAgile\NavOnlineSzamla\Enums\RequestStatus;
use IntrAgile\NavOnlineSzamla\Enums\Source;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
use IntrAgile\NavOnlineSzamla\Exceptions\XsdValidationException;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceChainQuery;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceDigestQuery;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceNumberQuery;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceSubmission;
use IntrAgile\NavOnlineSzamla\Requests\TechnicalAnnulment;
use IntrAgile\NavOnlineSzamla\Support\Payload;
use IntrAgile\NavOnlineSzamla\Tests\TestCase;
use IntrAgile\NavOnlineSzamla\Xml\Schema;
use IntrAgile\NavOnlineSzamla\Xml\XsdValidator;
use SimpleXMLElement;

final class OperationsTest extends TestCase
{
    use FakesNavResponses;

    private function sampleInvoice(string $name = 'Belfoldi termekertekesites'): string
    {
        return (string) file_get_contents(__DIR__.'/../../fixtures/nav-samples/'.$name.'.xml');
    }

    private function sentXml(Request $request): SimpleXMLElement
    {
        XsdValidator::validate($request->body(), Schema::Api);
        $xml = simplexml_load_string($request->body());
        $this->assertNotFalse($xml);

        return $xml;
    }

    public function test_manage_invoice_signs_every_index_and_returns_the_transaction_id(): void
    {
        Http::fake([
            self::URL.'/tokenExchange' => Http::response($this->tokenResponse()),
            self::URL.'/manageInvoice' => Http::response($this->transactionResponse('ManageInvoiceResponse', 'TX0000000001')),
        ]);

        $first = $this->sampleInvoice();
        $second = $this->sampleInvoice('Belfoldi ertekesites tobb AFA tipus');

        $transactionId = $this->client()->manageInvoice([InvoiceSubmission::create($first), InvoiceSubmission::create($second)]);

        $this->assertSame('TX0000000001', $transactionId);

        Http::assertSent(function (Request $request) use ($first, $second): bool {
            if (! str_ends_with($request->url(), '/manageInvoice')) {
                return false;
            }
            $xml = $this->sentXml($request);
            $common = $xml->children('http://schemas.nav.gov.hu/NTCA/1.0/common');

            $this->assertSame('decoded-token-value', (string) $xml->exchangeToken);
            $this->assertSame('false', (string) $xml->invoiceOperations->compressedContent);
            $this->assertSame('1', (string) $xml->invoiceOperations->invoiceOperation[0]->index);
            $this->assertSame('2', (string) $xml->invoiceOperations->invoiceOperation[1]->index);
            $this->assertSame($first, base64_decode((string) $xml->invoiceOperations->invoiceOperation[0]->invoiceData));

            $expected = Signer::requestSignature(
                (string) $common->header->requestId,
                new DateTimeImmutable((string) $common->header->timestamp),
                self::SIGN_KEY,
                [
                    ['operation' => 'CREATE', 'data' => base64_encode($first)],
                    ['operation' => 'CREATE', 'data' => base64_encode($second)],
                ],
            );
            $this->assertSame($expected, (string) $common->user->requestSignature);

            return true;
        });
    }

    public function test_manage_invoice_compresses_with_gzip_when_asked(): void
    {
        Http::fake([
            self::URL.'/tokenExchange' => Http::response($this->tokenResponse()),
            self::URL.'/manageInvoice' => Http::response($this->transactionResponse('ManageInvoiceResponse', 'TX0000000002')),
        ]);

        $invoice = $this->sampleInvoice();
        $this->client()->manageInvoice(InvoiceSubmission::create($invoice), compress: true);

        Http::assertSent(function (Request $request) use ($invoice): bool {
            if (! str_ends_with($request->url(), '/manageInvoice')) {
                return false;
            }
            $xml = $this->sentXml($request);
            $this->assertSame('true', (string) $xml->invoiceOperations->compressedContent);
            $this->assertSame($invoice, Payload::decode((string) $xml->invoiceOperations->invoiceOperation[0]->invoiceData, true));

            return true;
        });
    }

    public function test_manage_invoice_rejects_schema_invalid_invoices_before_any_request(): void
    {
        Http::fake();

        try {
            $this->client()->manageInvoice(InvoiceSubmission::create('<InvoiceData xmlns="http://schemas.nav.gov.hu/OSA/3.0/data"/>'));
            $this->fail('Expected an exception.');
        } catch (XsdValidationException $e) {
            $this->assertSame('invoiceData.xsd', $e->schema);
        }

        Http::assertNothingSent();
    }

    public function test_batch_size_is_limited_to_one_hundred(): void
    {
        Http::fake();

        $this->expectException(NavException::class);
        $this->client()->manageInvoice(array_fill(0, 101, InvoiceSubmission::create($this->sampleInvoice())));
    }

    public function test_manage_invoice_is_never_retried(): void
    {
        Http::fake([
            self::URL.'/tokenExchange' => Http::response($this->tokenResponse()),
            self::URL.'/manageInvoice' => Http::response($this->errorResponse('OPERATION_FAILED'), 500),
        ]);

        try {
            $this->client()->manageInvoice(InvoiceSubmission::create($this->sampleInvoice()));
        } catch (NavException) {
        }

        Http::assertSentCount(2);
    }

    public function test_manage_annulment_sends_a_schema_valid_annulment(): void
    {
        Http::fake([
            self::URL.'/tokenExchange' => Http::response($this->tokenResponse()),
            self::URL.'/manageAnnulment' => Http::response($this->transactionResponse('ManageAnnulmentResponse', 'TX0000000003')),
        ]);

        $annulment = new TechnicalAnnulment('2021/000123', AnnulmentCode::ErraticData, 'Wrong buyer', new DateTimeImmutable('2024-03-01T09:00:00Z'));

        $this->assertSame('TX0000000003', $this->client()->manageAnnulment($annulment));

        Http::assertSent(function (Request $request): bool {
            if (! str_ends_with($request->url(), '/manageAnnulment')) {
                return false;
            }
            $operation = $this->sentXml($request)->annulmentOperations->annulmentOperation[0];
            $inner = base64_decode((string) $operation->invoiceAnnulment);

            XsdValidator::validate($inner, Schema::Annulment);
            $this->assertSame('ANNUL', (string) $operation->annulmentOperation);
            $this->assertStringContainsString('<annulmentTimestamp>2024-03-01T09:00:00.000Z</annulmentTimestamp>', $inner);

            return true;
        });
    }

    public function test_query_transaction_status_maps_results_messages_and_original_request(): void
    {
        Http::fake([self::URL.'/queryTransactionStatus' => Http::response($this->recorded('queryTransactionStatus'))]);

        $status = $this->client()->queryTransactionStatus('5KP216T7BO9N167I', returnOriginalRequest: true);
        $result = $status->results[0];

        $this->assertTrue($status->isFinished());
        $this->assertSame('3.0', $status->originalRequestVersion);
        $this->assertSame(1, $result->index);
        $this->assertSame(InvoiceStatus::Done, $result->status);
        $this->assertTrue($result->isAccepted());
        $this->assertSame(BusinessResultCode::Warn, $result->warnings()[0]->resultCode);
        $this->assertSame('INCORRECT_HEAD_DATA_CUSTOMER_TAX_NUMBER', $result->warnings()[0]->errorCode);
        $this->assertSame([], $result->errors());
        $this->assertNotNull($result->originalRequest);
        XsdValidator::validate($result->originalRequest, Schema::Data);

        Http::assertSent(fn (Request $r): bool => (string) $this->sentXml($r)->returnOriginalRequest === 'true');
    }

    public function test_query_transaction_status_maps_annulment_data(): void
    {
        Http::fake([self::URL.'/queryTransactionStatus' => Http::response($this->recorded('queryTransactionStatus-annulment'))]);

        $status = $this->client()->queryTransactionStatus('5KP21BM7JR743TPO');

        $this->assertSame(AnnulmentVerificationStatus::VerificationPending, $status->annulmentStatus);
        $this->assertSame(InvoiceStatus::Done, $status->results[0]->status);
    }

    public function test_query_invoice_data_decodes_the_invoice(): void
    {
        Http::fake([self::URL.'/queryInvoiceData' => Http::response($this->recorded('queryInvoiceData'))]);

        $result = $this->client()->queryInvoiceData(InvoiceNumberQuery::outbound('E2E/20261105-091215'));

        $this->assertNotNull($result);
        XsdValidator::validate($result->invoiceXml, Schema::Data);
        $this->assertStringContainsString('E2E/20261105-091215', $result->invoiceXml);
        $this->assertSame('5KP216T7BO9N167I', $result->auditData->transactionId);
        $this->assertSame('abcdefghij12345', $result->auditData->insertedBy);
        $this->assertSame(Source::Mgm, $result->auditData->source);
        $this->assertSame('E2E/20261105-091215', $result->invoice()->invoiceNumber);

        Http::assertSent(function (Request $r): bool {
            $query = $this->sentXml($r)->invoiceNumberQuery;
            $this->assertSame('OUTBOUND', (string) $query->invoiceDirection);

            return true;
        });
    }

    public function test_query_invoice_data_returns_null_for_unknown_invoices(): void
    {
        Http::fake(['*' => Http::response($this->okResponse('QueryInvoiceDataResponse', ''))]);

        $this->assertNull($this->client()->queryInvoiceData(InvoiceNumberQuery::inbound('X-1', '87654321')));

        Http::assertSent(fn (Request $r): bool => (string) $this->sentXml($r)->invoiceNumberQuery->supplierTaxNumber === '87654321');
    }

    public function test_query_invoice_check(): void
    {
        Http::fake([self::URL.'/queryInvoiceCheck' => Http::response($this->recorded('queryInvoiceCheck'))]);

        $this->assertTrue($this->client()->queryInvoiceCheck(InvoiceNumberQuery::outbound('E2E/20261105-091215')));
    }

    public function test_query_invoice_digest_maps_the_page(): void
    {
        Http::fake([self::URL.'/queryInvoiceDigest' => Http::response($this->recorded('queryInvoiceDigest'))]);

        $page = $this->client()->queryInvoiceDigest(InvoiceDigestQuery::issuedBetween(new DateTimeImmutable('2026-11-04'), new DateTimeImmutable('2026-11-05')));
        $digest = $page->items[0];

        $this->assertSame(1, $page->currentPage);
        $this->assertFalse($page->hasMorePages());
        $this->assertSame('E2E/20261105-091215', $digest->invoiceNumber);
        $this->assertSame(InvoiceOperation::Create, $digest->operation);
        $this->assertSame('12345678', $digest->supplierTaxNumber);
        $this->assertSame('HUF', $digest->currency);
    }

    public function test_invoice_digest_query_builds_every_parameter_group_in_schema_order(): void
    {
        Http::fake(['*' => Http::response($this->recorded('queryInvoiceDigest'))]);

        $query = InvoiceDigestQuery::insertedBetween(new DateTimeImmutable('2024-03-01T00:00:00Z'), new DateTimeImmutable('2024-03-01T23:59:59Z'))
            ->taxNumber('87654321')
            ->name('Minta')
            ->currency('eur')
            ->paymentDate(QueryOperator::Gte, new DateTimeImmutable('2024-03-01'))
            ->paymentDate(QueryOperator::Lte, new DateTimeImmutable('2024-03-31'))
            ->netAmount(QueryOperator::Gt, 1000)
            ->transaction('TX0000000001', 1, InvoiceOperation::Create);

        $this->client()->queryInvoiceDigest($query, page: 2);

        Http::assertSent(function (Request $r): bool {
            $xml = $this->sentXml($r);
            $params = $xml->invoiceQueryParams;

            $this->assertSame('2', (string) $xml->page);
            $this->assertSame('2024-03-01T00:00:00.000Z', (string) $params->mandatoryQueryParams->insDate->dateTimeFrom);
            $this->assertSame('EUR', (string) $params->additionalQueryParams->currency);
            $this->assertCount(2, $params->relationalQueryParams->paymentDate);
            $this->assertSame('TX0000000001', (string) $params->transactionQueryParams->transactionId);

            return true;
        });
    }

    public function test_relational_parameters_allow_at_most_two_conditions(): void
    {
        $this->expectException(NavException::class);

        InvoiceDigestQuery::modificationsOf('A-1')
            ->netAmount(QueryOperator::Gt, 1)
            ->netAmount(QueryOperator::Lt, 9)
            ->netAmount(QueryOperator::Eq, 5);
    }

    public function test_lazy_pagination_fetches_every_page(): void
    {
        $page = fn (int $n) => str_replace(
            ['<ns2:currentPage>1</ns2:currentPage>', '<ns2:availablePage>1</ns2:availablePage>'],
            ["<ns2:currentPage>{$n}</ns2:currentPage>", '<ns2:availablePage>3</ns2:availablePage>'],
            $this->recorded('queryInvoiceDigest'),
        );
        Http::fake(['*' => Http::sequence()->push($page(1))->push($page(2))->push($page(3))]);

        $all = $this->client()->invoiceDigests(InvoiceDigestQuery::modificationsOf('A-1'));

        $this->assertCount(3, $all->all());
        Http::assertSentCount(3);
    }

    public function test_query_invoice_chain_digest(): void
    {
        Http::fake([self::URL.'/queryInvoiceChainDigest' => Http::response($this->recorded('queryInvoiceChainDigest'))]);

        $page = $this->client()->queryInvoiceChainDigest(new InvoiceChainQuery('E2E/20261105-091215'));

        $this->assertSame('E2E/20261105-091215', $page->items[0]->invoiceNumber);
        $this->assertSame(InvoiceOperation::Create, $page->items[0]->operation);
    }

    public function test_query_transaction_list(): void
    {
        Http::fake([self::URL.'/queryTransactionList' => Http::response($this->recorded('queryTransactionList'))]);

        $page = $this->client()->queryTransactionList(new DateTimeImmutable('-1 hour'), new DateTimeImmutable, status: RequestStatus::Finished);
        $ids = array_map(fn ($t) => $t->transactionId, $page->items);

        $this->assertContains('5KP216T7BO9N167I', $ids);
        Http::assertSent(fn (Request $r): bool => (string) $this->sentXml($r)->requestStatus === 'FINISHED');
    }

    public function test_query_taxpayer_maps_the_registered_data(): void
    {
        Http::fake([self::URL.'/queryTaxpayer' => Http::response($this->recorded('queryTaxpayer'))]);

        $taxpayer = $this->client()->queryTaxpayer('12345678-2-02');

        $this->assertNotNull($taxpayer);
        $this->assertTrue($taxpayer->valid);
        $this->assertSame('12345678-2-02', $taxpayer->taxNumber());
        $this->assertSame('MINTA KFT.', $taxpayer->shortName);
        $this->assertSame(Incorporation::Organization, $taxpayer->incorporation);
        $this->assertSame('BUDAPEST', $taxpayer->headquarters()?->city);

        Http::assertSent(fn (Request $r): bool => (string) $this->sentXml($r)->taxNumber === '12345678');
    }

    public function test_query_taxpayer_returns_null_for_unknown_tax_numbers(): void
    {
        Http::fake(['*' => Http::response($this->okResponse('QueryTaxpayerResponse', '<taxpayerValidity>false</taxpayerValidity>'))]);

        $this->assertNull($this->client()->queryTaxpayer('00000001'));
    }

    public function test_query_taxpayer_rejects_malformed_tax_numbers_without_calling_nav(): void
    {
        Http::fake();

        try {
            $this->client()->queryTaxpayer('123');
            $this->fail('Expected an exception.');
        } catch (NavException) {
        }
        Http::assertNothingSent();
    }
}
