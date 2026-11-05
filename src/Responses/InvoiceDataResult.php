<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Responses;

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
}
