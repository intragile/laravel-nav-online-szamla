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
 * VAT data of given tax rate.
 *
 * Generated from VatRateVatDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class VatRateVatData implements ModelElement
{
    /**
     * @param  Decimal  $vatRateVatAmount  VAT amount of sales or service delivery under a given tax rate expressed in the currency of the invoice.
     * @param  Decimal  $vatRateVatAmountHUF  VAT amount of sales or service delivery under a given tax rate expressed in HUF.
     */
    public function __construct(
        public Decimal $vatRateVatAmount,
        public Decimal $vatRateVatAmountHUF,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            vatRateVatAmount: $reader->one(Ns::DATA, 'vatRateVatAmount', Value::decimal(...)),
            vatRateVatAmountHUF: $reader->one(Ns::DATA, 'vatRateVatAmountHUF', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'vatRateVatAmount', $this->vatRateVatAmount->value);
        $writer->text(Ns::DATA, 'vatRateVatAmountHUF', $this->vatRateVatAmountHUF->value);
    }
}
