<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Requests;

use IntrAgile\NavOnlineSzamla\Enums\InvoiceDirection;

/**
 * Identifies the base invoice of a modification chain for queryInvoiceChainDigest.
 */
final readonly class InvoiceChainQuery
{
    public function __construct(
        public string $invoiceNumber,
        public InvoiceDirection $direction = InvoiceDirection::Outbound,
        public ?string $taxNumber = null,
    ) {}
}
