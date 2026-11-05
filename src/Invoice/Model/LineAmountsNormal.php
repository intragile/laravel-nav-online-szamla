<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Item value data to be completed in case of normal or aggregate invoice.
 *
 * Generated from LineAmountsNormalType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class LineAmountsNormal implements ModelElement
{
    /**
     * @param  LineNetAmountData  $lineNetAmountData  Line net data.
     * @param  VatRate  $lineVatRate  Tax rate or tax exemption marking.
     * @param  LineVatData|null  $lineVatData  Line VAT data.
     * @param  LineGrossAmountData|null  $lineGrossAmountData  Line gross data.
     */
    public function __construct(
        public LineNetAmountData $lineNetAmountData,
        public VatRate $lineVatRate,
        public ?LineVatData $lineVatData = null,
        public ?LineGrossAmountData $lineGrossAmountData = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            lineNetAmountData: $reader->one(Ns::DATA, 'lineNetAmountData', LineNetAmountData::fromXml(...)),
            lineVatRate: $reader->one(Ns::DATA, 'lineVatRate', VatRate::fromXml(...)),
            lineVatData: $reader->optional(Ns::DATA, 'lineVatData', LineVatData::fromXml(...)),
            lineGrossAmountData: $reader->optional(Ns::DATA, 'lineGrossAmountData', LineGrossAmountData::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::DATA, 'lineNetAmountData', $this->lineNetAmountData);
        $writer->child(Ns::DATA, 'lineVatRate', $this->lineVatRate);
        $writer->child(Ns::DATA, 'lineVatData', $this->lineVatData);
        $writer->child(Ns::DATA, 'lineGrossAmountData', $this->lineGrossAmountData);
    }
}
