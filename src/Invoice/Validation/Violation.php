<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Validation;

/**
 * A blocking NAV business validation the invoice data would fail.
 */
final readonly class Violation
{
    /**
     * @param  string  $code  The validationErrorCode NAV would return.
     * @param  string  $path  Location of the offending data below InvoiceData.
     */
    public function __construct(
        public string $code,
        public string $message,
        public string $path,
    ) {}
}
