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
 * Line net data.
 *
 * Generated from LineNetAmountDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class LineNetAmountData implements ModelElement
{
    /**
     * @param  Decimal  $lineNetAmount  Net amount of the item expressed in the currency of the invoice. In case of margin scheme taxation containing VAT, the amount of consideration reduced with the amount of VAT, expressed in the currency of the invoice.
     * @param  Decimal  $lineNetAmountHUF  Net amount of the item expressed in HUF. Net amount of the item expressed in the currency of the invoice. In case of margin scheme taxation containing VAT, the amount of consideration reduced with the amount of VAT, expressed in HUF.
     */
    public function __construct(
        public Decimal $lineNetAmount,
        public Decimal $lineNetAmountHUF,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            lineNetAmount: $reader->one(Ns::DATA, 'lineNetAmount', Value::decimal(...)),
            lineNetAmountHUF: $reader->one(Ns::DATA, 'lineNetAmountHUF', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'lineNetAmount', $this->lineNetAmount->value);
        $writer->text(Ns::DATA, 'lineNetAmountHUF', $this->lineNetAmountHUF->value);
    }
}
