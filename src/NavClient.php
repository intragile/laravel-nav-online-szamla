<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\LazyCollection;
use IntrAgile\NavOnlineSzamla\Config\Credentials;
use IntrAgile\NavOnlineSzamla\Config\Software;
use IntrAgile\NavOnlineSzamla\Crypto\TokenDecoder;
use IntrAgile\NavOnlineSzamla\Enums\RequestStatus;
use IntrAgile\NavOnlineSzamla\Exceptions\NavApiException;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
use IntrAgile\NavOnlineSzamla\Exceptions\TransportException;
use IntrAgile\NavOnlineSzamla\Http\Transport;
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
use IntrAgile\NavOnlineSzamla\Support\Payload;
use IntrAgile\NavOnlineSzamla\Support\RequestIdGenerator;
use IntrAgile\NavOnlineSzamla\Support\Timestamp;
use IntrAgile\NavOnlineSzamla\Xml\RequestDocument;
use IntrAgile\NavOnlineSzamla\Xml\ResponseDocument;
use IntrAgile\NavOnlineSzamla\Xml\ResponseMapper;
use IntrAgile\NavOnlineSzamla\Xml\Schema;
use IntrAgile\NavOnlineSzamla\Xml\XsdValidator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * NAV Online Számla client for one taxpayer.
 */
class NavClient
{
    /** @var Closure(): DateTimeImmutable */
    private Closure $clock;

    public function __construct(
        public readonly Credentials $credentials,
        public readonly Software $software,
        private readonly Transport $transport,
        private readonly RequestIdGenerator $requestIds,
        private readonly bool $validateXsd = true,
        private readonly int $retries = 2,
        private readonly int $retryDelayMs = 500,
        private readonly LoggerInterface $logger = new NullLogger,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): DateTimeImmutable => Timestamp::now();
    }

    /**
     * Request and decode a data exchange token.
     */
    public function tokenExchange(): ExchangeToken
    {
        $response = $this->call('tokenExchange', 'TokenExchangeRequest', idempotent: true);

        return new ExchangeToken(
            TokenDecoder::decode($response->requireString('/*/api:encodedExchangeToken'), $this->credentials->exchangeKey),
            new DateTimeImmutable($response->requireString('/*/api:tokenValidityFrom')),
            new DateTimeImmutable($response->requireString('/*/api:tokenValidityTo')),
        );
    }

    /**
     * Submit up to 100 invoices in one transaction and return its transaction ID.
     *
     * Every invoice is validated against invoiceData.xsd first. The payload is compressed
     * when requested, or automatically when the request would exceed NAV's size limit.
     *
     * @param  InvoiceSubmission|list<InvoiceSubmission>  $invoices
     */
    public function manageInvoice(InvoiceSubmission|array $invoices, ?bool $compress = null): string
    {
        $invoices = is_array($invoices) ? $invoices : [$invoices];
        $this->assertBatchSize(count($invoices));

        foreach ($invoices as $invoice) {
            XsdValidator::validate($invoice->invoiceXml, Schema::Data);
        }

        $compress ??= array_sum(array_map(static fn (InvoiceSubmission $i): int => strlen($i->invoiceXml), $invoices))
            > (int) (Payload::MAX_REQUEST_BYTES * 0.7);

        $operations = array_map(
            static fn (InvoiceSubmission $i): array => ['operation' => $i->operation->value, 'data' => Payload::encode($i->invoiceXml, $compress)],
            $invoices,
        );

        $token = $this->tokenExchange()->token;

        $response = $this->call('manageInvoice', 'ManageInvoiceRequest', function (RequestDocument $request) use ($token, $operations, $invoices, $compress): void {
            $root = $request->root();
            $request->add($root, 'exchangeToken', $token);
            $list = $request->add($root, 'invoiceOperations');
            $request->add($list, 'compressedContent', $compress ? 'true' : 'false');

            foreach ($operations as $i => $operation) {
                $item = $request->add($list, 'invoiceOperation');
                $request->add($item, 'index', (string) ($i + 1));
                $request->add($item, 'invoiceOperation', $operation['operation']);
                $request->add($item, 'invoiceData', $operation['data']);
                if ($invoices[$i]->electronicInvoiceHash !== null) {
                    $request->add($item, 'electronicInvoiceHash', $invoices[$i]->electronicInvoiceHash)
                        ->setAttribute('cryptoType', $invoices[$i]->electronicInvoiceHashAlgorithm->value);
                }
            }
        }, $operations);

        return $response->requireString('/*/api:transactionId');
    }

    /**
     * Request the technical annulment of up to 100 invoices and return the transaction ID.
     *
     * @param  TechnicalAnnulment|list<TechnicalAnnulment>  $annulments
     */
    public function manageAnnulment(TechnicalAnnulment|array $annulments): string
    {
        $annulments = is_array($annulments) ? $annulments : [$annulments];
        $this->assertBatchSize(count($annulments));

        $operations = array_map(static function (TechnicalAnnulment $a): array {
            $xml = $a->toXml();
            XsdValidator::validate($xml, Schema::Annulment);

            return ['operation' => 'ANNUL', 'data' => base64_encode($xml)];
        }, $annulments);

        $token = $this->tokenExchange()->token;

        $response = $this->call('manageAnnulment', 'ManageAnnulmentRequest', function (RequestDocument $request) use ($token, $operations): void {
            $root = $request->root();
            $request->add($root, 'exchangeToken', $token);
            $list = $request->add($root, 'annulmentOperations');

            foreach ($operations as $i => $operation) {
                $item = $request->add($list, 'annulmentOperation');
                $request->add($item, 'index', (string) ($i + 1));
                $request->add($item, 'annulmentOperation', $operation['operation']);
                $request->add($item, 'invoiceAnnulment', $operation['data']);
            }
        }, $operations);

        return $response->requireString('/*/api:transactionId');
    }

    public function queryTransactionStatus(string $transactionId, bool $returnOriginalRequest = false): TransactionStatus
    {
        $response = $this->call('queryTransactionStatus', 'QueryTransactionStatusRequest', function (RequestDocument $request) use ($transactionId, $returnOriginalRequest): void {
            $request->add($request->root(), 'transactionId', $transactionId);
            if ($returnOriginalRequest) {
                $request->add($request->root(), 'returnOriginalRequest', 'true');
            }
        }, idempotent: true);

        return ResponseMapper::transactionStatus($response, $transactionId);
    }

    /**
     * Full data of one invoice, or null when NAV has no such invoice.
     */
    public function queryInvoiceData(InvoiceNumberQuery $query): ?InvoiceDataResult
    {
        return ResponseMapper::invoiceData(
            $this->call('queryInvoiceData', 'QueryInvoiceDataRequest', fn (RequestDocument $r) => $this->appendInvoiceNumberQuery($r, $query), idempotent: true),
        );
    }

    /**
     * Whether NAV knows the invoice.
     */
    public function queryInvoiceCheck(InvoiceNumberQuery $query): bool
    {
        $response = $this->call('queryInvoiceCheck', 'QueryInvoiceCheckRequest', fn (RequestDocument $r) => $this->appendInvoiceNumberQuery($r, $query), idempotent: true);

        return $response->bool('/*/api:invoiceCheckResult') ?? false;
    }

    /**
     * @return Page<InvoiceDigest>
     */
    public function queryInvoiceDigest(InvoiceDigestQuery $query, int $page = 1): Page
    {
        $response = $this->call('queryInvoiceDigest', 'QueryInvoiceDigestRequest', function (RequestDocument $request) use ($query, $page): void {
            $request->add($request->root(), 'page', (string) $page);
            $request->add($request->root(), 'invoiceDirection', $query->direction->value);
            $query->appendTo($request, $request->root());
        }, idempotent: true);

        return ResponseMapper::invoiceDigestPage($response);
    }

    /**
     * Every matching invoice digest, fetching pages lazily.
     *
     * @return LazyCollection<int, InvoiceDigest>
     */
    public function invoiceDigests(InvoiceDigestQuery $query): LazyCollection
    {
        return $this->paginate(fn (int $page): Page => $this->queryInvoiceDigest($query, $page));
    }

    /**
     * @return Page<InvoiceChainElement>
     */
    public function queryInvoiceChainDigest(InvoiceChainQuery $query, int $page = 1): Page
    {
        $response = $this->call('queryInvoiceChainDigest', 'QueryInvoiceChainDigestRequest', function (RequestDocument $request) use ($query, $page): void {
            $request->add($request->root(), 'page', (string) $page);
            $chain = $request->add($request->root(), 'invoiceChainQuery');
            $request->add($chain, 'invoiceNumber', $query->invoiceNumber);
            $request->add($chain, 'invoiceDirection', $query->direction->value);
            $request->addOptional($chain, 'taxNumber', $query->taxNumber);
        }, idempotent: true);

        return ResponseMapper::invoiceChainPage($response);
    }

    /**
     * @return LazyCollection<int, InvoiceChainElement>
     */
    public function invoiceChain(InvoiceChainQuery $query): LazyCollection
    {
        return $this->paginate(fn (int $page): Page => $this->queryInvoiceChainDigest($query, $page));
    }

    /**
     * Transactions submitted between two instants (NAV allows at most 24 hours per query).
     *
     * @return Page<Transaction>
     */
    public function queryTransactionList(DateTimeInterface $from, DateTimeInterface $to, int $page = 1, ?RequestStatus $status = null): Page
    {
        $response = $this->call('queryTransactionList', 'QueryTransactionListRequest', function (RequestDocument $request) use ($from, $to, $page, $status): void {
            $request->add($request->root(), 'page', (string) $page);
            $range = $request->add($request->root(), 'insDate');
            $request->add($range, 'dateTimeFrom', Timestamp::header($from));
            $request->add($range, 'dateTimeTo', Timestamp::header($to));
            $request->addOptional($request->root(), 'requestStatus', $status?->value);
        }, idempotent: true);

        return ResponseMapper::transactionPage($response);
    }

    /**
     * @return LazyCollection<int, Transaction>
     */
    public function transactions(DateTimeInterface $from, DateTimeInterface $to, ?RequestStatus $status = null): LazyCollection
    {
        return $this->paginate(fn (int $page): Page => $this->queryTransactionList($from, $to, $page, $status));
    }

    /**
     * Registered data of a Hungarian taxpayer, or null when the tax number is unknown.
     *
     * Accepts any tax number format; only the first 8 digits are sent.
     */
    public function queryTaxpayer(string $taxNumber): ?Taxpayer
    {
        $taxpayerId = substr(preg_replace('/\D/', '', $taxNumber) ?? '', 0, 8);
        if (strlen($taxpayerId) !== 8) {
            throw new NavException('A Hungarian tax number starts with 8 digits.');
        }

        $response = $this->call('queryTaxpayer', 'QueryTaxpayerRequest', function (RequestDocument $request) use ($taxpayerId): void {
            $request->add($request->root(), 'taxNumber', $taxpayerId);
        }, idempotent: true);

        return ResponseMapper::taxpayer($response);
    }

    /**
     * Build, validate, sign and send one request.
     *
     * Idempotent calls (queries and tokenExchange) are resent with a new request ID on
     * transient errors. manageInvoice and manageAnnulment must never be retried here: after
     * a network failure NAV may already have accepted them.
     *
     * @param  (Closure(RequestDocument): void)|null  $body
     * @param  list<array{operation: string, data: string}>  $signedOperations
     */
    public function call(
        string $operation,
        string $rootElement,
        ?Closure $body = null,
        array $signedOperations = [],
        bool $idempotent = false,
    ): ResponseDocument {
        $attempt = 0;

        while (true) {
            $attempt++;
            $requestId = $this->requestIds->generate();
            $request = new RequestDocument($rootElement, $this->credentials, $this->software, $requestId, ($this->clock)(), $signedOperations);
            if ($body !== null) {
                $body($request);
            }
            $xml = $request->toXml();

            if ($this->validateXsd) {
                XsdValidator::validate($xml, Schema::Api);
            }

            $started = hrtime(true);
            try {
                $response = $this->transport->post($operation, $requestId, $xml);
                $this->log('info', $operation, $requestId, $started, $attempt);

                return $response;
            } catch (NavApiException|TransportException $e) {
                $retryable = $idempotent
                    && $attempt <= $this->retries
                    && ($e instanceof TransportException || $e->isRetryable());

                $this->log($retryable ? 'warning' : 'error', $operation, $requestId, $started, $attempt, $e);

                if (! $retryable) {
                    throw $e;
                }

                usleep($this->retryDelayMs * 1000 * $attempt);
            }
        }
    }

    private function appendInvoiceNumberQuery(RequestDocument $request, InvoiceNumberQuery $query): void
    {
        $node = $request->add($request->root(), 'invoiceNumberQuery');
        $request->add($node, 'invoiceNumber', $query->invoiceNumber);
        $request->add($node, 'invoiceDirection', $query->direction->value);
        $request->addOptional($node, 'batchIndex', $query->batchIndex === null ? null : (string) $query->batchIndex);
        $request->addOptional($node, 'supplierTaxNumber', $query->supplierTaxNumber);
    }

    private function assertBatchSize(int $count): void
    {
        if ($count < 1 || $count > 100) {
            throw new NavException("A NAV request carries 1 to 100 documents, {$count} given.");
        }
    }

    /**
     * @template T
     *
     * @param  Closure(int): Page<T>  $fetch
     * @return LazyCollection<int, T>
     */
    private function paginate(Closure $fetch): LazyCollection
    {
        return LazyCollection::make(function () use ($fetch) {
            $page = 1;
            do {
                $result = $fetch($page);
                // Yield one by one: `yield from` would repeat the keys of every page.
                foreach ($result->items as $item) {
                    yield $item;
                }
                $page++;
            } while ($result->hasMorePages());
        });
    }

    private function log(string $level, string $operation, string $requestId, int|float $started, int $attempt, ?\Throwable $error = null): void
    {
        // Request bodies are never logged: they contain the password hash and exchange token.
        $this->logger->log($level, "NAV {$operation}", array_filter([
            'request_id' => $requestId,
            'tax_number' => $this->credentials->taxNumber,
            'attempt' => $attempt,
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'error_code' => $error instanceof NavApiException ? $error->errorCode : null,
            'error' => $error?->getMessage(),
        ], static fn ($v) => $v !== null));
    }
}
