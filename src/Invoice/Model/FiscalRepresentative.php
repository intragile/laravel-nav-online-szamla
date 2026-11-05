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
 * Fiscal representative data.
 *
 * Generated from FiscalRepresentativeType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class FiscalRepresentative implements ModelElement
{
    /**
     * @param  TaxNumber  $fiscalRepresentativeTaxNumber  Tax number of the fiscal representative.
     * @param  string  $fiscalRepresentativeName  Name of the fiscal representative.
     * @param  Address  $fiscalRepresentativeAddress  Address of the fiscal representative.
     * @param  string|null  $fiscalRepresentativeBankAccountNumber  Bank account number opened by the fiscal representative for the issuer of the invoice (supplier).
     */
    public function __construct(
        public TaxNumber $fiscalRepresentativeTaxNumber,
        public string $fiscalRepresentativeName,
        public Address $fiscalRepresentativeAddress,
        public ?string $fiscalRepresentativeBankAccountNumber = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            fiscalRepresentativeTaxNumber: $reader->one(Ns::DATA, 'fiscalRepresentativeTaxNumber', TaxNumber::fromXml(...)),
            fiscalRepresentativeName: $reader->one(Ns::DATA, 'fiscalRepresentativeName', Value::string(...)),
            fiscalRepresentativeAddress: $reader->one(Ns::DATA, 'fiscalRepresentativeAddress', Address::fromXml(...)),
            fiscalRepresentativeBankAccountNumber: $reader->optional(Ns::DATA, 'fiscalRepresentativeBankAccountNumber', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::DATA, 'fiscalRepresentativeTaxNumber', $this->fiscalRepresentativeTaxNumber);
        $writer->text(Ns::DATA, 'fiscalRepresentativeName', $this->fiscalRepresentativeName);
        $writer->child(Ns::DATA, 'fiscalRepresentativeAddress', $this->fiscalRepresentativeAddress);
        $writer->text(Ns::DATA, 'fiscalRepresentativeBankAccountNumber', $this->fiscalRepresentativeBankAccountNumber);
    }
}
