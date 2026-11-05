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
 * Invoice supplier (seller) data.
 *
 * Generated from SupplierInfoType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class SupplierInfo implements ModelElement
{
    /**
     * @param  TaxNumber  $supplierTaxNumber  Tax number or group identification number.
     * @param  string  $supplierName  Name of the seller (supplier).
     * @param  Address  $supplierAddress  Address of the seller (supplier).
     * @param  TaxNumber|null  $groupMemberTaxNumber  Tax number of group member, when the supply of goods or services is done under group identification number.
     * @param  string|null  $communityVatNumber  Community tax number.
     * @param  string|null  $supplierBankAccountNumber  Bank account number of the seller (supplier).
     * @param  bool|null  $individualExemption  Value is true if the seller (supplier) is individually exempted from VAT.
     * @param  string|null  $exciseLicenceNum  Number of supplier’s tax warehouse license or excise license (Act LXVIII of 2016).
     */
    public function __construct(
        public TaxNumber $supplierTaxNumber,
        public string $supplierName,
        public Address $supplierAddress,
        public ?TaxNumber $groupMemberTaxNumber = null,
        public ?string $communityVatNumber = null,
        public ?string $supplierBankAccountNumber = null,
        public ?bool $individualExemption = null,
        public ?string $exciseLicenceNum = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            supplierTaxNumber: $reader->one(Ns::DATA, 'supplierTaxNumber', TaxNumber::fromXml(...)),
            groupMemberTaxNumber: $reader->optional(Ns::DATA, 'groupMemberTaxNumber', TaxNumber::fromXml(...)),
            communityVatNumber: $reader->optional(Ns::DATA, 'communityVatNumber', Value::string(...)),
            supplierName: $reader->one(Ns::DATA, 'supplierName', Value::string(...)),
            supplierAddress: $reader->one(Ns::DATA, 'supplierAddress', Address::fromXml(...)),
            supplierBankAccountNumber: $reader->optional(Ns::DATA, 'supplierBankAccountNumber', Value::string(...)),
            individualExemption: $reader->optional(Ns::DATA, 'individualExemption', Value::bool(...)),
            exciseLicenceNum: $reader->optional(Ns::DATA, 'exciseLicenceNum', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::DATA, 'supplierTaxNumber', $this->supplierTaxNumber);
        $writer->child(Ns::DATA, 'groupMemberTaxNumber', $this->groupMemberTaxNumber);
        $writer->text(Ns::DATA, 'communityVatNumber', $this->communityVatNumber);
        $writer->text(Ns::DATA, 'supplierName', $this->supplierName);
        $writer->child(Ns::DATA, 'supplierAddress', $this->supplierAddress);
        $writer->text(Ns::DATA, 'supplierBankAccountNumber', $this->supplierBankAccountNumber);
        $writer->text(Ns::DATA, 'individualExemption', Value::fromBool($this->individualExemption));
        $writer->text(Ns::DATA, 'exciseLicenceNum', $this->exciseLicenceNum);
    }
}
