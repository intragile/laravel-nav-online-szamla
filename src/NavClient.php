<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla;

use Closure;
use DateTimeImmutable;
use IntrAgile\NavOnlineSzamla\Config\Credentials;
use IntrAgile\NavOnlineSzamla\Config\Software;
use IntrAgile\NavOnlineSzamla\Crypto\TokenDecoder;
use IntrAgile\NavOnlineSzamla\Exceptions\NavApiException;
use IntrAgile\NavOnlineSzamla\Exceptions\TransportException;
use IntrAgile\NavOnlineSzamla\Http\Transport;
use IntrAgile\NavOnlineSzamla\Responses\ExchangeToken;
use IntrAgile\NavOnlineSzamla\Support\RequestIdGenerator;
use IntrAgile\NavOnlineSzamla\Support\Timestamp;
use IntrAgile\NavOnlineSzamla\Xml\RequestDocument;
use IntrAgile\NavOnlineSzamla\Xml\ResponseDocument;
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
