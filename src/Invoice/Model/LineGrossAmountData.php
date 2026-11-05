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
 * Line gross data.
 *
 * Generated from LineGrossAmountDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class LineGrossAmountData implements ModelElement
{
    /**
     * @param  Decimal  $lineGrossAmountNormal  Gross amount of the item expressed in the currency of the invoice. In case of margin scheme taxation containing VAT, the amount of consideration expressed in the currency of the invoice.
     * @param  Decimal  $lineGrossAmountNormalHUF  Gross amount of the item expressed in HUF.
     */
    public function __construct(
        public Decimal $lineGrossAmountNormal,
        public Decimal $lineGrossAmountNormalHUF,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            lineGrossAmountNormal: $reader->one(Ns::DATA, 'lineGrossAmountNormal', Value::decimal(...)),
            lineGrossAmountNormalHUF: $reader->one(Ns::DATA, 'lineGrossAmountNormalHUF', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'lineGrossAmountNormal', $this->lineGrossAmountNormal->value);
        $writer->text(Ns::DATA, 'lineGrossAmountNormalHUF', $this->lineGrossAmountNormalHUF->value);
    }
}
