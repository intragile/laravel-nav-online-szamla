<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Responses;

use DateTimeImmutable;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceAppearance;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceCategory;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceOperation;
use IntrAgile\NavOnlineSzamla\Enums\PaymentMethod;
use IntrAgile\NavOnlineSzamla\Enums\Source;

/**
 * Summary of one invoice returned by queryInvoiceDigest.
 *
 * Monetary amounts are decimal strings, exactly as returned by NAV.
 */
final readonly class InvoiceDigest
{
    public function __construct(
        public string $invoiceNumber,
        public ?int $batchIndex,
        public InvoiceOperation $operation,
        public InvoiceCategory $category,
        public DateTimeImmutable $issueDate,
        public string $supplierTaxNumber,
        public ?string $supplierGroupMemberTaxNumber,
        public string $supplierName,
        public ?string $customerTaxNumber,
        public ?string $customerGroupMemberTaxNumber,
        public ?string $customerName,
        public ?PaymentMethod $paymentMethod,
        public ?DateTimeImmutable $paymentDate,
        public ?InvoiceAppearance $appearance,
        public ?Source $source,
        public ?DateTimeImmutable $deliveryDate,
        public ?string $currency,
        public ?string $netAmount,
        public ?string $netAmountHuf,
        public ?string $vatAmount,
        public ?string $vatAmountHuf,
        public ?string $transactionId,
        public ?int $index,
        public ?string $originalInvoiceNumber,
        public ?int $modificationIndex,
        public DateTimeImmutable $insertedAt,
        public ?bool $completenessIndicator,
    ) {}
}
