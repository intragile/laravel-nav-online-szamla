<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Data of a batch of modification documents.
 *
 * Generated from BatchInvoiceType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class BatchInvoice implements ModelElement
{
    /**
     * @param  int  $batchIndex  Sequence number of the modification document within the batch.
     * @param  Invoice  $invoice  Data of a single invoice or modification document.
     */
    public function __construct(
        public int $batchIndex,
        public Invoice $invoice,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            batchIndex: $reader->one(Ns::DATA, 'batchIndex', Value::int(...)),
            invoice: $reader->one(Ns::DATA, 'invoice', Invoice::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'batchIndex', (string) $this->batchIndex);
        $writer->child(Ns::DATA, 'invoice', $this->invoice);
    }
}
