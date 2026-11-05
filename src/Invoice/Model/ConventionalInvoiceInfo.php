<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Other conventionally named data to assist in invoice processing.
 *
 * Generated from ConventionalInvoiceInfoType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class ConventionalInvoiceInfo implements ModelElement
{
    /**
     * @param  OrderNumbers|null  $orderNumbers  Order numbers.
     * @param  DeliveryNotes|null  $deliveryNotes  Delivery notes.
     * @param  ShippingDates|null  $shippingDates  Shipping dates.
     * @param  ContractNumbers|null  $contractNumbers  Contract numbers.
     * @param  SupplierCompanyCodes|null  $supplierCompanyCodes  Company codes of the supplier.
     * @param  CustomerCompanyCodes|null  $customerCompanyCodes  Company codes of the customer.
     * @param  DealerCodes|null  $dealerCodes  Dealer codes.
     * @param  CostCenters|null  $costCenters  Cost centers.
     * @param  ProjectNumbers|null  $projectNumbers  Project numbers.
     * @param  GeneralLedgerAccountNumbers|null  $generalLedgerAccountNumbers  General ledger account numbers.
     * @param  GlnNumbers|null  $glnNumbersSupplier  Supplier's global location numbers.
     * @param  GlnNumbers|null  $glnNumbersCustomer  Customer's global location numbers.
     * @param  MaterialNumbers|null  $materialNumbers  Material numbers.
     * @param  ItemNumbers|null  $itemNumbers  Item number(s).
     * @param  EkaerIds|null  $ekaerIds  EKAER ID-s.
     */
    public function __construct(
        public ?OrderNumbers $orderNumbers = null,
        public ?DeliveryNotes $deliveryNotes = null,
        public ?ShippingDates $shippingDates = null,
        public ?ContractNumbers $contractNumbers = null,
        public ?SupplierCompanyCodes $supplierCompanyCodes = null,
        public ?CustomerCompanyCodes $customerCompanyCodes = null,
        public ?DealerCodes $dealerCodes = null,
        public ?CostCenters $costCenters = null,
        public ?ProjectNumbers $projectNumbers = null,
        public ?GeneralLedgerAccountNumbers $generalLedgerAccountNumbers = null,
        public ?GlnNumbers $glnNumbersSupplier = null,
        public ?GlnNumbers $glnNumbersCustomer = null,
        public ?MaterialNumbers $materialNumbers = null,
        public ?ItemNumbers $itemNumbers = null,
        public ?EkaerIds $ekaerIds = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            orderNumbers: $reader->optional(Ns::DATA, 'orderNumbers', OrderNumbers::fromXml(...)),
            deliveryNotes: $reader->optional(Ns::DATA, 'deliveryNotes', DeliveryNotes::fromXml(...)),
            shippingDates: $reader->optional(Ns::DATA, 'shippingDates', ShippingDates::fromXml(...)),
            contractNumbers: $reader->optional(Ns::DATA, 'contractNumbers', ContractNumbers::fromXml(...)),
            supplierCompanyCodes: $reader->optional(Ns::DATA, 'supplierCompanyCodes', SupplierCompanyCodes::fromXml(...)),
            customerCompanyCodes: $reader->optional(Ns::DATA, 'customerCompanyCodes', CustomerCompanyCodes::fromXml(...)),
            dealerCodes: $reader->optional(Ns::DATA, 'dealerCodes', DealerCodes::fromXml(...)),
            costCenters: $reader->optional(Ns::DATA, 'costCenters', CostCenters::fromXml(...)),
            projectNumbers: $reader->optional(Ns::DATA, 'projectNumbers', ProjectNumbers::fromXml(...)),
            generalLedgerAccountNumbers: $reader->optional(Ns::DATA, 'generalLedgerAccountNumbers', GeneralLedgerAccountNumbers::fromXml(...)),
            glnNumbersSupplier: $reader->optional(Ns::DATA, 'glnNumbersSupplier', GlnNumbers::fromXml(...)),
            glnNumbersCustomer: $reader->optional(Ns::DATA, 'glnNumbersCustomer', GlnNumbers::fromXml(...)),
            materialNumbers: $reader->optional(Ns::DATA, 'materialNumbers', MaterialNumbers::fromXml(...)),
            itemNumbers: $reader->optional(Ns::DATA, 'itemNumbers', ItemNumbers::fromXml(...)),
            ekaerIds: $reader->optional(Ns::DATA, 'ekaerIds', EkaerIds::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::DATA, 'orderNumbers', $this->orderNumbers);
        $writer->child(Ns::DATA, 'deliveryNotes', $this->deliveryNotes);
        $writer->child(Ns::DATA, 'shippingDates', $this->shippingDates);
        $writer->child(Ns::DATA, 'contractNumbers', $this->contractNumbers);
        $writer->child(Ns::DATA, 'supplierCompanyCodes', $this->supplierCompanyCodes);
        $writer->child(Ns::DATA, 'customerCompanyCodes', $this->customerCompanyCodes);
        $writer->child(Ns::DATA, 'dealerCodes', $this->dealerCodes);
        $writer->child(Ns::DATA, 'costCenters', $this->costCenters);
        $writer->child(Ns::DATA, 'projectNumbers', $this->projectNumbers);
        $writer->child(Ns::DATA, 'generalLedgerAccountNumbers', $this->generalLedgerAccountNumbers);
        $writer->child(Ns::DATA, 'glnNumbersSupplier', $this->glnNumbersSupplier);
        $writer->child(Ns::DATA, 'glnNumbersCustomer', $this->glnNumbersCustomer);
        $writer->child(Ns::DATA, 'materialNumbers', $this->materialNumbers);
        $writer->child(Ns::DATA, 'itemNumbers', $this->itemNumbers);
        $writer->child(Ns::DATA, 'ekaerIds', $this->ekaerIds);
    }
}
