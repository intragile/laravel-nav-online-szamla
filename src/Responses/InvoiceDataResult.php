<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Responses;

use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;

final readonly class InvoiceDataResult
{
    /**
     * @param  string  $invoiceXml  The InvoiceData document, already base64-decoded and decompressed.
     */
    public function __construct(
        public string $invoiceXml,
        public AuditData $auditData,
        public bool $compressedContent,
        public ?string $electronicInvoiceHash = null,
    ) {}

    /**
     * The invoice data as the typed model.
     */
    public function invoice(): InvoiceData
    {
        return InvoiceData::fromXmlString($this->invoiceXml);
    }
}
