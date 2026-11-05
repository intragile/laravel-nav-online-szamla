<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Testing;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use IntrAgile\NavOnlineSzamla\Config\Credentials;
use IntrAgile\NavOnlineSzamla\Config\Software;
use IntrAgile\NavOnlineSzamla\Enums\RequestStatus;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
use IntrAgile\NavOnlineSzamla\Http\Transport;
use IntrAgile\NavOnlineSzamla\NavClient;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceChainQuery;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceDigestQuery;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceNumberQuery;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceSubmission;
use IntrAgile\NavOnlineSzamla\Requests\TechnicalAnnulment;
use IntrAgile\NavOnlineSzamla\Responses\ExchangeToken;
use IntrAgile\NavOnlineSzamla\Responses\InvoiceChainElement;
use IntrAgile\NavOnlineSzamla\Responses\InvoiceDataResult;
use IntrAgile\NavOnlineSzamla\Responses\InvoiceDigest;
use IntrAgile\NavOnlineSzamla\Responses\Page;
use IntrAgile\NavOnlineSzamla\Responses\Taxpayer;
use IntrAgile\NavOnlineSzamla\Responses\Transaction;
use IntrAgile\NavOnlineSzamla\Responses\TransactionStatus;
use IntrAgile\NavOnlineSzamla\Support\UlidRequestIdGenerator;
use IntrAgile\NavOnlineSzamla\Xml\ResponseDocument;
use IntrAgile\NavOnlineSzamla\Xml\Schema;
use IntrAgile\NavOnlineSzamla\Xml\XsdValidator;
use LogicException;

/**
 * NavClient answering from the in-memory NAV of a NavFake instead of the network.
 *
 * Outgoing documents are still validated against the XSDs, so a test fails on data the
 * real NAV would refuse.
 */
final class FakeNavClient extends NavClient
{
    public function __construct(
        Credentials $credentials,
        Software $software,
        Transport $transport,
        private readonly NavFake $fake,
        private readonly ?string $tenant,
    ) {
        parent::__construct($credentials, $software, $transport, new UlidRequestIdGenerator);
    }

    public function tokenExchange(): ExchangeToken
    {
        return $this->handle('tokenExchange', [], fn (): ExchangeToken => new ExchangeToken(
            'fake-exchange-token',
            new DateTimeImmutable,
            new DateTimeImmutable('+5 minutes'),
        ), ExchangeToken::class);
    }

    public function manageInvoice(InvoiceSubmission|array $invoices, ?bool $compress = null): string
    {
        $invoices = is_array($invoices) ? $invoices : [$invoices];

        return $this->handle('manageInvoice', [$invoices, $compress], function () use ($invoices): string {
            $this->assertBatch(count($invoices));
            foreach ($invoices as $invoice) {
                XsdValidator::validate($invoice->invoiceXml, Schema::Data);
            }
            $this->fake->recordSubmittedInvoices($invoices, $this->tenant);

            return $this->fake->storeInvoices($this->credentials, $invoices);
        }, 'string');
    }

    public function manageAnnulment(TechnicalAnnulment|array $annulments): string
    {
        $annulments = is_array($annulments) ? $annulments : [$annulments];

        return $this->handle('manageAnnulment', [$annulments], function () use ($annulments): string {
            $this->assertBatch(count($annulments));
            foreach ($annulments as $annulment) {
                XsdValidator::validate($annulment->toXml(), Schema::Annulment);
            }
            $this->fake->recordSubmittedAnnulments($annulments, $this->tenant);

            return $this->fake->storeAnnulments($this->credentials, $annulments);
        }, 'string');
    }

    public function queryTransactionStatus(string $transactionId, bool $returnOriginalRequest = false): TransactionStatus
    {
        return $this->handle(
            'queryTransactionStatus',
            [$transactionId, $returnOriginalRequest],
            fn (): TransactionStatus => $this->fake->transactionStatus($transactionId, $returnOriginalRequest),
            TransactionStatus::class,
        );
    }

    public function queryInvoiceData(InvoiceNumberQuery $query): ?InvoiceDataResult
    {
        return $this->handle('queryInvoiceData', [$query], fn (): ?InvoiceDataResult => $this->fake->invoiceData($this->credentials, $query), InvoiceDataResult::class, nullable: true);
    }

    public function queryInvoiceCheck(InvoiceNumberQuery $query): bool
    {
        return $this->handle('queryInvoiceCheck', [$query], fn (): bool => $this->fake->invoiceData($this->credentials, $query) !== null, 'bool');
    }

    /**
     * @return Page<InvoiceDigest>
     */
    public function queryInvoiceDigest(InvoiceDigestQuery $query, int $page = 1): Page
    {
        /** @var Page<InvoiceDigest> */
        return $this->handle('queryInvoiceDigest', [$query, $page], static fn (): Page => new Page(1, 1, []), Page::class);
    }

    /**
     * @return Page<InvoiceChainElement>
     */
    public function queryInvoiceChainDigest(InvoiceChainQuery $query, int $page = 1): Page
    {
        /** @var Page<InvoiceChainElement> */
        return $this->handle('queryInvoiceChainDigest', [$query, $page], static fn (): Page => new Page(1, 1, []), Page::class);
    }

    /**
     * @return Page<Transaction>
     */
    public function queryTransactionList(DateTimeInterface $from, DateTimeInterface $to, int $page = 1, ?RequestStatus $status = null): Page
    {
        /** @var Page<Transaction> */
        return $this->handle(
            'queryTransactionList',
            [$from, $to, $page, $status],
            fn (): Page => new Page(1, 1, $this->fake->transactionList($this->credentials, $from, $to, $status)),
            Page::class,
        );
    }

    public function queryTaxpayer(string $taxNumber): ?Taxpayer
    {
        return $this->handle('queryTaxpayer', [$taxNumber], fn (): ?Taxpayer => $this->fake->taxpayerFor($taxNumber), Taxpayer::class, nullable: true);
    }

    public function call(
        string $operation,
        string $rootElement,
        ?Closure $body = null,
        array $signedOperations = [],
        bool $idempotent = false,
    ): ResponseDocument {
        throw new LogicException('Raw calls are not supported by Nav::fake(); use the typed operations or Nav::fake()->respond().');
    }

    /**
     * Record the call, then answer with a queued failure, a registered response or the default.
     *
     * @template T
     *
     * @param  list<mixed>  $arguments
     * @param  Closure(): T  $default
     * @param  class-string|'string'|'bool'  $type
     * @return T
     */
    private function handle(string $operation, array $arguments, Closure $default, string $type, bool $nullable = false): mixed
    {
        $this->fake->recordCall(new RecordedCall($operation, $arguments, $this->tenant));

        if (! $this->fake->hasResponse($operation)) {
            return $default();
        }

        $value = $this->fake->response($operation, $arguments);
        $valid = ($nullable && $value === null) || match ($type) {
            'string' => is_string($value),
            'bool' => is_bool($value),
            default => $value instanceof $type,
        };

        if (! $valid) {
            throw new LogicException(sprintf('The faked response of %s must be %s%s, %s given.', $operation, $nullable ? '?' : '', $type, get_debug_type($value)));
        }

        /** @var T $value */
        return $value;
    }

    private function assertBatch(int $count): void
    {
        if ($count < 1 || $count > 100) {
            throw new NavException("A NAV request carries 1 to 100 documents, {$count} given.");
        }
    }
}
