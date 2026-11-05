<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Support\Decimal;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Marking tax rate or tax exempt supply.
 *
 * Generated from VatRateType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class VatRate implements ModelElement
{
    /**
     * @param  Decimal|null  $vatPercentage  Applied tax rate - section 169 (j) of the VAT law.
     * @param  Decimal|null  $vatContent  VAT content in case of simplified invoice.
     * @param  DetailedReason|null  $vatExemption  Marking tax exemption - section 169 (m) of the VAT law.
     * @param  DetailedReason|null  $vatOutOfScope  Out of scope of the VAT law.
     * @param  bool|null  $vatDomesticReverseCharge  Marking the national is reverse charge taxation - section 142 of the VAT law.
     * @param  MarginScheme|null  $marginSchemeIndicator  Marking the margin-scheme taxation as per section 169 (p)(q).
     * @param  VatAmountMismatch|null  $vatAmountMismatch  Different cases of mismatching tax base and levied tax.
     * @param  bool|null  $noVatCharge  No VAT charged under Section 17.
     */
    public function __construct(
        public ?Decimal $vatPercentage = null,
        public ?Decimal $vatContent = null,
        public ?DetailedReason $vatExemption = null,
        public ?DetailedReason $vatOutOfScope = null,
        public ?bool $vatDomesticReverseCharge = null,
        public ?MarginScheme $marginSchemeIndicator = null,
        public ?VatAmountMismatch $vatAmountMismatch = null,
        public ?bool $noVatCharge = null,
    ) {
        if ((($this->vatPercentage !== null ? 1 : 0) + ($this->vatContent !== null ? 1 : 0) + ($this->vatExemption !== null ? 1 : 0) + ($this->vatOutOfScope !== null ? 1 : 0) + ($this->vatDomesticReverseCharge !== null ? 1 : 0) + ($this->marginSchemeIndicator !== null ? 1 : 0) + ($this->vatAmountMismatch !== null ? 1 : 0) + ($this->noVatCharge !== null ? 1 : 0)) !== 1) {
            throw new InvoiceModelException('VatRate: Exactly one of vatPercentage, vatContent, vatExemption, vatOutOfScope, vatDomesticReverseCharge, marginSchemeIndicator, vatAmountMismatch, noVatCharge must be set.');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            vatPercentage: $reader->optional(Ns::DATA, 'vatPercentage', Value::decimal(...)),
            vatContent: $reader->optional(Ns::DATA, 'vatContent', Value::decimal(...)),
            vatExemption: $reader->optional(Ns::DATA, 'vatExemption', DetailedReason::fromXml(...)),
            vatOutOfScope: $reader->optional(Ns::DATA, 'vatOutOfScope', DetailedReason::fromXml(...)),
            vatDomesticReverseCharge: $reader->optional(Ns::DATA, 'vatDomesticReverseCharge', Value::bool(...)),
            marginSchemeIndicator: $reader->optional(Ns::DATA, 'marginSchemeIndicator', static fn (DOMElement $e): MarginScheme => MarginScheme::from(Value::string($e))),
            vatAmountMismatch: $reader->optional(Ns::DATA, 'vatAmountMismatch', VatAmountMismatch::fromXml(...)),
            noVatCharge: $reader->optional(Ns::DATA, 'noVatCharge', Value::bool(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'vatPercentage', $this->vatPercentage?->value);
        $writer->text(Ns::DATA, 'vatContent', $this->vatContent?->value);
        $writer->child(Ns::DATA, 'vatExemption', $this->vatExemption);
        $writer->child(Ns::DATA, 'vatOutOfScope', $this->vatOutOfScope);
        $writer->text(Ns::DATA, 'vatDomesticReverseCharge', Value::fromBool($this->vatDomesticReverseCharge));
        $writer->text(Ns::DATA, 'marginSchemeIndicator', $this->marginSchemeIndicator?->value);
        $writer->child(Ns::DATA, 'vatAmountMismatch', $this->vatAmountMismatch);
        $writer->text(Ns::DATA, 'noVatCharge', Value::fromBool($this->noVatCharge));
    }
}
