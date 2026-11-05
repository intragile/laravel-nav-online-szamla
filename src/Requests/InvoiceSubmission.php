<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Requests;

use IntrAgile\NavOnlineSzamla\Crypto\HashAlgorithm;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceOperation;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;

/**
 * One invoice in a manageInvoice request.
 */
final readonly class InvoiceSubmission
{
    /**
     * @param  string  $invoiceXml  An InvoiceData document conforming to invoiceData.xsd.
     * @param  string|null  $electronicInvoiceHash  Required when completenessIndicator is true.
     */
    public function __construct(
        public InvoiceOperation $operation,
        public string $invoiceXml,
        public ?string $electronicInvoiceHash = null,
        public HashAlgorithm $electronicInvoiceHashAlgorithm = HashAlgorithm::Sha3_512,
    ) {}

    /**
     * Submission of typed invoice data, e.g. from InvoiceBuilder::build().
     */
    public static function of(InvoiceOperation $operation, InvoiceData $invoice, ?string $electronicInvoiceHash = null): self
    {
        return new self($operation, $invoice->toXml(), $electronicInvoiceHash);
    }

    public static function create(string $invoiceXml, ?string $electronicInvoiceHash = null): self
    {
        return new self(InvoiceOperation::Create, $invoiceXml, $electronicInvoiceHash);
    }

    public static function modify(string $invoiceXml, ?string $electronicInvoiceHash = null): self
    {
        return new self(InvoiceOperation::Modify, $invoiceXml, $electronicInvoiceHash);
    }

    public static function storno(string $invoiceXml, ?string $electronicInvoiceHash = null): self
    {
        return new self(InvoiceOperation::Storno, $invoiceXml, $electronicInvoiceHash);
    }
}
