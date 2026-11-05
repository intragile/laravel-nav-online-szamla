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
 * Item value data to be completed in case of simplified invoice.
 *
 * Generated from LineAmountsSimplifiedType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class LineAmountsSimplified implements ModelElement
{
    /**
     * @param  VatRate  $lineVatRate  Tax rate or tax exemption marking.
     * @param  Decimal  $lineGrossAmountSimplified  Gross amount of the item expressed in the currency of the invoice.
     * @param  Decimal  $lineGrossAmountSimplifiedHUF  Gross amount of the item expressed in HUF.
     */
    public function __construct(
        public VatRate $lineVatRate,
        public Decimal $lineGrossAmountSimplified,
        public Decimal $lineGrossAmountSimplifiedHUF,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            lineVatRate: $reader->one(Ns::DATA, 'lineVatRate', VatRate::fromXml(...)),
            lineGrossAmountSimplified: $reader->one(Ns::DATA, 'lineGrossAmountSimplified', Value::decimal(...)),
            lineGrossAmountSimplifiedHUF: $reader->one(Ns::DATA, 'lineGrossAmountSimplifiedHUF', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::DATA, 'lineVatRate', $this->lineVatRate);
        $writer->text(Ns::DATA, 'lineGrossAmountSimplified', $this->lineGrossAmountSimplified->value);
        $writer->text(Ns::DATA, 'lineGrossAmountSimplifiedHUF', $this->lineGrossAmountSimplifiedHUF->value);
    }
}
