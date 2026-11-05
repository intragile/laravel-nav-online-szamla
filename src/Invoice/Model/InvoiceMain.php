<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * A common type to describe invoice information.
 *
 * Generated from InvoiceMainType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class InvoiceMain implements ModelElement
{
    /**
     * @param  Invoice|null  $invoice  Data of a single invoice or modification document.
     * @param  list<BatchInvoice>  $batchInvoice  Data of a batch of modification documents.
     */
    public function __construct(
        public ?Invoice $invoice = null,
        public array $batchInvoice = [],
    ) {
        if ((($this->invoice !== null ? 1 : 0) + ($this->batchInvoice !== [] ? 1 : 0)) !== 1) {
            throw new InvoiceModelException('InvoiceMain: Exactly one of invoice, batchInvoice must be set.');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            invoice: $reader->optional(Ns::DATA, 'invoice', Invoice::fromXml(...)),
            batchInvoice: $reader->many(Ns::DATA, 'batchInvoice', BatchInvoice::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::DATA, 'invoice', $this->invoice);
        $writer->children(Ns::DATA, 'batchInvoice', $this->batchInvoice);
    }
}
