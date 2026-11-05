<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * VAT subjectivity data of the customer.
 *
 * Generated from CustomerVatDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class CustomerVatData implements ModelElement
{
    /**
     * @param  CustomerTaxNumber|null  $customerTaxNumber  Domestic tax number or group identification number, under which the purchase of goods or services is done.
     * @param  string|null  $communityVatNumber  Community tax number.
     * @param  string|null  $thirdStateTaxId  Third state tax identification number.
     */
    public function __construct(
        public ?CustomerTaxNumber $customerTaxNumber = null,
        public ?string $communityVatNumber = null,
        public ?string $thirdStateTaxId = null,
    ) {
        if ((($this->customerTaxNumber !== null ? 1 : 0) + ($this->communityVatNumber !== null ? 1 : 0) + ($this->thirdStateTaxId !== null ? 1 : 0)) !== 1) {
            throw new InvoiceModelException('CustomerVatData: Exactly one of customerTaxNumber, communityVatNumber, thirdStateTaxId must be set.');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            customerTaxNumber: $reader->optional(Ns::DATA, 'customerTaxNumber', CustomerTaxNumber::fromXml(...)),
            communityVatNumber: $reader->optional(Ns::DATA, 'communityVatNumber', Value::string(...)),
            thirdStateTaxId: $reader->optional(Ns::DATA, 'thirdStateTaxId', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::DATA, 'customerTaxNumber', $this->customerTaxNumber);
        $writer->text(Ns::DATA, 'communityVatNumber', $this->communityVatNumber);
        $writer->text(Ns::DATA, 'thirdStateTaxId', $this->thirdStateTaxId);
    }
}
