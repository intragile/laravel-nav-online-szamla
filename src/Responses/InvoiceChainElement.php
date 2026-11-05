<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Responses;

use DateTimeImmutable;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceOperation;

/**
 * One document of an invoice modification chain (queryInvoiceChainDigest).
 */
final readonly class InvoiceChainElement
{
    /**
     * @param  list<array{start: int, end: int}>  $newCreatedLines
     */
    public function __construct(
        public string $invoiceNumber,
        public ?int $batchIndex,
        public InvoiceOperation $operation,
        public string $supplierTaxNumber,
        public ?string $customerTaxNumber,
        public DateTimeImmutable $insertedAt,
        public string $originalRequestVersion,
        public ?int $maxLineNumber = null,
        public array $newCreatedLines = [],
        public ?string $originalInvoiceNumber = null,
        public ?bool $modifyWithoutMaster = null,
        public ?DateTimeImmutable $modificationTimestamp = null,
        public ?int $modificationIndex = null,
    ) {}
}
