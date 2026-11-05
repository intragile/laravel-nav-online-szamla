<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Exceptions;

use Throwable;

/**
 * The request did not reach NAV, or the response was lost.
 *
 * For manageInvoice and manageAnnulment the outcome is unknown after a timeout: NAV may
 * have accepted the submission. Do not resend blindly; reconcile first via
 * queryTransactionList or queryInvoiceCheck.
 */
final class TransportException extends NavException
{
    public function __construct(
        string $message,
        public readonly string $operation,
        public readonly string $requestId,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
