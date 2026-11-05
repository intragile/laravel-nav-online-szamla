<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Data in header of invoice.
 *
 * Generated from InvoiceHeadType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class InvoiceHead implements ModelElement
{
    /**
     * @param  SupplierInfo  $supplierInfo  Data related to the issuer of the invoice (supplier).
     * @param  InvoiceDetail  $invoiceDetail  Invoice detail adata.
     * @param  CustomerInfo|null  $customerInfo  Data related to the customer.
     * @param  FiscalRepresentative|null  $fiscalRepresentativeInfo  Data related to the fiscal representative.
     */
    public function __construct(
        public SupplierInfo $supplierInfo,
        public InvoiceDetail $invoiceDetail,
        public ?CustomerInfo $customerInfo = null,
        public ?FiscalRepresentative $fiscalRepresentativeInfo = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            supplierInfo: $reader->one(Ns::DATA, 'supplierInfo', SupplierInfo::fromXml(...)),
            customerInfo: $reader->optional(Ns::DATA, 'customerInfo', CustomerInfo::fromXml(...)),
            fiscalRepresentativeInfo: $reader->optional(Ns::DATA, 'fiscalRepresentativeInfo', FiscalRepresentative::fromXml(...)),
            invoiceDetail: $reader->one(Ns::DATA, 'invoiceDetail', InvoiceDetail::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::DATA, 'supplierInfo', $this->supplierInfo);
        $writer->child(Ns::DATA, 'customerInfo', $this->customerInfo);
        $writer->child(Ns::DATA, 'fiscalRepresentativeInfo', $this->fiscalRepresentativeInfo);
        $writer->child(Ns::DATA, 'invoiceDetail', $this->invoiceDetail);
    }
}
