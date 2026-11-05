<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use IntrAgile\NavOnlineSzamla\Exceptions\NavApiException;
use IntrAgile\NavOnlineSzamla\Exceptions\TransportException;
use IntrAgile\NavOnlineSzamla\Xml\ResponseDocument;

/**
 * Sends a request through the Laravel HTTP client, so Http::fake(), Telescope and Pulse
 * all see NAV traffic.
 *
 * There is deliberately no retry here: a resend needs a new request ID, timestamp and
 * signature, so retries are handled by NavClient.
 */
final class Transport
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $baseUrl,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
    ) {}

    /**
     * @throws TransportException
     * @throws NavApiException
     */
    public function post(string $operation, string $requestId, string $xml): ResponseDocument
    {
        try {
            $response = $this->http
                ->withHeaders(['Content-Type' => 'application/xml', 'Accept' => 'application/xml'])
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->withBody($xml, 'application/xml')
                ->post(rtrim($this->baseUrl, '/').'/'.$operation);
        } catch (ConnectionException $e) {
            throw new TransportException("NAV {$operation}: connection failed ({$e->getMessage()})", $operation, $requestId, $e);
        }

        $body = $response->body();
        $document = ResponseDocument::tryParse($body);

        if ($document === null) {
            throw new NavApiException(
                sprintf('NAV %s: non-XML response, HTTP %d', $operation, $response->status()),
                $operation, $requestId, $response->status(), responseBody: $body,
            );
        }

        $isErrorRoot = in_array($document->rootName(), ['GeneralErrorResponse', 'GeneralExceptionResponse'], true);
        if ($isErrorRoot || $document->funcCode() === 'ERROR' || ! $response->successful()) {
            $code = $document->errorCode();
            throw new NavApiException(
                sprintf('NAV %s: %s%s', $operation, $code ?? 'HTTP '.$response->status(), $document->message() ? ' – '.$document->message() : ''),
                $operation, $requestId, $response->status(), $code, $document->technicalValidationMessages(), $body,
            );
        }

        return $document;
    }
}
