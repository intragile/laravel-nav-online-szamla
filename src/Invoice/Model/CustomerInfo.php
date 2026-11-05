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
 * Customer data.
 *
 * Generated from CustomerInfoType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class CustomerInfo implements ModelElement
{
    /**
     * @param  CustomerVatStatus  $customerVatStatus  Customers status by VAT.
     * @param  CustomerVatData|null  $customerVatData  VAT subjectivity data of the customer.
     * @param  string|null  $customerName  Name of the customer.
     * @param  Address|null  $customerAddress  Address of the customer.
     * @param  string|null  $customerBankAccountNumber  Bank account number of the customer.
     */
    public function __construct(
        public CustomerVatStatus $customerVatStatus,
        public ?CustomerVatData $customerVatData = null,
        public ?string $customerName = null,
        public ?Address $customerAddress = null,
        public ?string $customerBankAccountNumber = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            customerVatStatus: $reader->one(Ns::DATA, 'customerVatStatus', static fn (DOMElement $e): CustomerVatStatus => CustomerVatStatus::from(Value::string($e))),
            customerVatData: $reader->optional(Ns::DATA, 'customerVatData', CustomerVatData::fromXml(...)),
            customerName: $reader->optional(Ns::DATA, 'customerName', Value::string(...)),
            customerAddress: $reader->optional(Ns::DATA, 'customerAddress', Address::fromXml(...)),
            customerBankAccountNumber: $reader->optional(Ns::DATA, 'customerBankAccountNumber', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'customerVatStatus', $this->customerVatStatus->value);
        $writer->child(Ns::DATA, 'customerVatData', $this->customerVatData);
        $writer->text(Ns::DATA, 'customerName', $this->customerName);
        $writer->child(Ns::DATA, 'customerAddress', $this->customerAddress);
        $writer->text(Ns::DATA, 'customerBankAccountNumber', $this->customerBankAccountNumber);
    }
}
