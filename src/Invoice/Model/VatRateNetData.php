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
 * Net data of given tax rate.
 *
 * Generated from VatRateNetDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class VatRateNetData implements ModelElement
{
    /**
     * @param  Decimal  $vatRateNetAmount  Net amount of sales or service delivery under a given tax rate expressed in the currency of the invoice.
     * @param  Decimal  $vatRateNetAmountHUF  Net amount of sales or service delivery under a given tax rate expressed in HUF.
     */
    public function __construct(
        public Decimal $vatRateNetAmount,
        public Decimal $vatRateNetAmountHUF,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            vatRateNetAmount: $reader->one(Ns::DATA, 'vatRateNetAmount', Value::decimal(...)),
            vatRateNetAmountHUF: $reader->one(Ns::DATA, 'vatRateNetAmountHUF', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'vatRateNetAmount', $this->vatRateNetAmount->value);
        $writer->text(Ns::DATA, 'vatRateNetAmountHUF', $this->vatRateNetAmountHUF->value);
    }
}
