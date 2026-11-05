<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Summary according to VAT rates.
 *
 * Generated from SummaryByVatRateType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class SummaryByVatRate implements ModelElement
{
    /**
     * @param  VatRate  $vatRate  Marking the tax rate or the fact of tax exemption.
     * @param  VatRateNetData  $vatRateNetData  Net data of given tax rate.
     * @param  VatRateVatData  $vatRateVatData  VAT data of given tax rate.
     * @param  VatRateGrossData|null  $vatRateGrossData  Gross data of given tax rate.
     */
    public function __construct(
        public VatRate $vatRate,
        public VatRateNetData $vatRateNetData,
        public VatRateVatData $vatRateVatData,
        public ?VatRateGrossData $vatRateGrossData = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            vatRate: $reader->one(Ns::DATA, 'vatRate', VatRate::fromXml(...)),
            vatRateNetData: $reader->one(Ns::DATA, 'vatRateNetData', VatRateNetData::fromXml(...)),
            vatRateVatData: $reader->one(Ns::DATA, 'vatRateVatData', VatRateVatData::fromXml(...)),
            vatRateGrossData: $reader->optional(Ns::DATA, 'vatRateGrossData', VatRateGrossData::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::DATA, 'vatRate', $this->vatRate);
        $writer->child(Ns::DATA, 'vatRateNetData', $this->vatRateNetData);
        $writer->child(Ns::DATA, 'vatRateVatData', $this->vatRateVatData);
        $writer->child(Ns::DATA, 'vatRateGrossData', $this->vatRateGrossData);
    }
}
