<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Exceptions;

/**
 * NAV answered with an error: a GeneralErrorResponse, a GeneralExceptionResponse,
 * or a non-XML error body. Error codes follow the "Error handling" table of the
 * interface specification.
 */
class NavApiException extends NavException
{
    private const RETRYABLE_CODES = ['OPERATION_FAILED', 'MAINTENANCE_MODE'];

    private const AUTHENTICATION_CODES = [
        'INVALID_SECURITY_USER', 'NOT_REGISTERED_CUSTOMER', 'INVALID_CUSTOMER',
        'INVALID_USER_RELATION', 'FORBIDDEN', 'INVALID_REQUEST_SIGNATURE',
        'INVALID_EXCHANGE_TOKEN', 'INVALID_PASSWORD_HASH_CRYPTO',
    ];

    /**
     * @param  list<TechnicalValidationMessage>  $technicalValidationMessages
     */
    public function __construct(
        string $message,
        public readonly string $operation,
        public readonly string $requestId,
        public readonly int $httpStatus,
        public readonly ?string $errorCode = null,
        public readonly array $technicalValidationMessages = [],
        public readonly ?string $responseBody = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Whether the error is transient and the request may be resent with a new request ID.
     */
    public function isRetryable(): bool
    {
        if ($this->errorCode !== null) {
            return in_array($this->errorCode, self::RETRYABLE_CODES, true);
        }

        // No NAV error code means an infrastructure failure (gateway, application server).
        return $this->httpStatus >= 500;
    }

    /**
     * Whether the error is caused by credentials or permissions; retrying cannot help.
     */
    public function isAuthenticationError(): bool
    {
        return in_array($this->errorCode, self::AUTHENTICATION_CODES, true);
    }
}
