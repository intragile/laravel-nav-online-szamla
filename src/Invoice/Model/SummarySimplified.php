<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Support\Decimal;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Calculation of simplified invoice totals.
 *
 * Generated from SummarySimplifiedType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class SummarySimplified implements ModelElement
{
    /**
     * @param  VatRate  $vatRate  Marking the tax rate or the fact of tax exemption.
     * @param  Decimal  $vatContentGrossAmount  The gross amount of the sale or service for the given tax amount in the currency of the invoice.
     * @param  Decimal  $vatContentGrossAmountHUF  The gross amount of the sale or service for the given tax amount in HUF.
     */
    public function __construct(
        public VatRate $vatRate,
        public Decimal $vatContentGrossAmount,
        public Decimal $vatContentGrossAmountHUF,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            vatRate: $reader->one(Ns::DATA, 'vatRate', VatRate::fromXml(...)),
            vatContentGrossAmount: $reader->one(Ns::DATA, 'vatContentGrossAmount', Value::decimal(...)),
            vatContentGrossAmountHUF: $reader->one(Ns::DATA, 'vatContentGrossAmountHUF', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::DATA, 'vatRate', $this->vatRate);
        $writer->text(Ns::DATA, 'vatContentGrossAmount', $this->vatContentGrossAmount->value);
        $writer->text(Ns::DATA, 'vatContentGrossAmountHUF', $this->vatContentGrossAmountHUF->value);
    }
}
