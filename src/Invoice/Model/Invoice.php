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
 * Data of a single invoice or modification document.
 *
 * Generated from InvoiceType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class Invoice implements ModelElement
{
    /**
     * @param  InvoiceHead  $invoiceHead  Data concerning the whole invoice.
     * @param  Summary  $invoiceSummary  Summary data according to VAT law.
     * @param  InvoiceReference|null  $invoiceReference  Modification or cancellation data.
     * @param  Lines|null  $invoiceLines  Product/service data appearing on the invoice.
     * @param  list<ProductFeeSummary>  $productFeeSummary  Summary data of product charges.
     */
    public function __construct(
        public InvoiceHead $invoiceHead,
        public Summary $invoiceSummary,
        public ?InvoiceReference $invoiceReference = null,
        public ?Lines $invoiceLines = null,
        public array $productFeeSummary = [],
    ) {
        if (count($this->productFeeSummary) > 2) {
            throw new InvoiceModelException('Invoice: productFeeSummary allows at most 2 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            invoiceReference: $reader->optional(Ns::DATA, 'invoiceReference', InvoiceReference::fromXml(...)),
            invoiceHead: $reader->one(Ns::DATA, 'invoiceHead', InvoiceHead::fromXml(...)),
            invoiceLines: $reader->optional(Ns::DATA, 'invoiceLines', Lines::fromXml(...)),
            productFeeSummary: $reader->many(Ns::DATA, 'productFeeSummary', ProductFeeSummary::fromXml(...)),
            invoiceSummary: $reader->one(Ns::DATA, 'invoiceSummary', Summary::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::DATA, 'invoiceReference', $this->invoiceReference);
        $writer->child(Ns::DATA, 'invoiceHead', $this->invoiceHead);
        $writer->child(Ns::DATA, 'invoiceLines', $this->invoiceLines);
        $writer->children(Ns::DATA, 'productFeeSummary', $this->productFeeSummary);
        $writer->child(Ns::DATA, 'invoiceSummary', $this->invoiceSummary);
    }
}
