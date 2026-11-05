<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Requests;

use IntrAgile\NavOnlineSzamla\Enums\InvoiceDirection;

/**
 * Identifies one invoice for queryInvoiceData and queryInvoiceCheck.
 */
final readonly class InvoiceNumberQuery
{
    /**
     * @param  int|null  $batchIndex  Position within a batch modification document.
     * @param  string|null  $supplierTaxNumber  Supplier tax number (8 digits); inbound queries only.
     */
    public function __construct(
        public string $invoiceNumber,
        public InvoiceDirection $direction = InvoiceDirection::Outbound,
        public ?int $batchIndex = null,
        public ?string $supplierTaxNumber = null,
    ) {}

    public static function outbound(string $invoiceNumber, ?int $batchIndex = null): self
    {
        return new self($invoiceNumber, InvoiceDirection::Outbound, $batchIndex);
    }

    public static function inbound(string $invoiceNumber, ?string $supplierTaxNumber = null, ?int $batchIndex = null): self
    {
        return new self($invoiceNumber, InvoiceDirection::Inbound, $batchIndex, $supplierTaxNumber);
    }
}
