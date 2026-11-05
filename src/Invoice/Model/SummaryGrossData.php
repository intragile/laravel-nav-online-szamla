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
 * Gross data of the invoice summary.
 *
 * Generated from SummaryGrossDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class SummaryGrossData implements ModelElement
{
    /**
     * @param  Decimal  $invoiceGrossAmount  Gross amount of the invoice expressed in the currency of the invoice.
     * @param  Decimal  $invoiceGrossAmountHUF  Gross amount of the invoice expressed in HUF.
     */
    public function __construct(
        public Decimal $invoiceGrossAmount,
        public Decimal $invoiceGrossAmountHUF,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            invoiceGrossAmount: $reader->one(Ns::DATA, 'invoiceGrossAmount', Value::decimal(...)),
            invoiceGrossAmountHUF: $reader->one(Ns::DATA, 'invoiceGrossAmountHUF', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'invoiceGrossAmount', $this->invoiceGrossAmount->value);
        $writer->text(Ns::DATA, 'invoiceGrossAmountHUF', $this->invoiceGrossAmountHUF->value);
    }
}
