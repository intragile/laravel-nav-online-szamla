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
 * Gross data of given tax rate.
 *
 * Generated from VatRateGrossDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class VatRateGrossData implements ModelElement
{
    /**
     * @param  Decimal  $vatRateGrossAmount  Gross amount of sales or service delivery under a given tax rate expressed in the currency of the invoice.
     * @param  Decimal  $vatRateGrossAmountHUF  Gross amount of sales or service delivery under a given tax rate expressed in HUF.
     */
    public function __construct(
        public Decimal $vatRateGrossAmount,
        public Decimal $vatRateGrossAmountHUF,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            vatRateGrossAmount: $reader->one(Ns::DATA, 'vatRateGrossAmount', Value::decimal(...)),
            vatRateGrossAmountHUF: $reader->one(Ns::DATA, 'vatRateGrossAmountHUF', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'vatRateGrossAmount', $this->vatRateGrossAmount->value);
        $writer->text(Ns::DATA, 'vatRateGrossAmountHUF', $this->vatRateGrossAmountHUF->value);
    }
}
