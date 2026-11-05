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
 * Line VAT data.
 *
 * Generated from LineVatDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class LineVatData implements ModelElement
{
    /**
     * @param  Decimal  $lineVatAmount  VAT amount of the item expressed in the currency of the invoice.
     * @param  Decimal  $lineVatAmountHUF  VAT amount of the item expressed in HUF.
     */
    public function __construct(
        public Decimal $lineVatAmount,
        public Decimal $lineVatAmountHUF,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            lineVatAmount: $reader->one(Ns::DATA, 'lineVatAmount', Value::decimal(...)),
            lineVatAmountHUF: $reader->one(Ns::DATA, 'lineVatAmountHUF', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'lineVatAmount', $this->lineVatAmount->value);
        $writer->text(Ns::DATA, 'lineVatAmountHUF', $this->lineVatAmountHUF->value);
    }
}
