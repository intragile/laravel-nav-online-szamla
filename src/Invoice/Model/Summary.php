<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Data of calculation of invoice totals.
 *
 * Generated from SummaryType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class Summary implements ModelElement
{
    /**
     * @param  SummaryNormal|null  $summaryNormal  Calculation of invoice totals (not simplified invoice).
     * @param  list<SummarySimplified>  $summarySimplified  Calculation of simplified invoice totals.
     * @param  SummaryGrossData|null  $summaryGrossData  Gross data of the invoice summary.
     */
    public function __construct(
        public ?SummaryNormal $summaryNormal = null,
        public array $summarySimplified = [],
        public ?SummaryGrossData $summaryGrossData = null,
    ) {
        if ((($this->summaryNormal !== null ? 1 : 0) + ($this->summarySimplified !== [] ? 1 : 0)) !== 1) {
            throw new InvoiceModelException('Summary: Exactly one of summaryNormal, summarySimplified must be set.');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            summaryNormal: $reader->optional(Ns::DATA, 'summaryNormal', SummaryNormal::fromXml(...)),
            summarySimplified: $reader->many(Ns::DATA, 'summarySimplified', SummarySimplified::fromXml(...)),
            summaryGrossData: $reader->optional(Ns::DATA, 'summaryGrossData', SummaryGrossData::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::DATA, 'summaryNormal', $this->summaryNormal);
        $writer->children(Ns::DATA, 'summarySimplified', $this->summarySimplified);
        $writer->child(Ns::DATA, 'summaryGrossData', $this->summaryGrossData);
    }
}
