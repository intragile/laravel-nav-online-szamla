<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Product / service items.
 *
 * Generated from LinesType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class Lines implements ModelElement
{
    /**
     * @param  bool  $mergedItemIndicator  Indicates whether the data exchange contains merged line data due to size reduction.
     * @param  list<Line>  $line  Product / service item.
     */
    public function __construct(
        public bool $mergedItemIndicator,
        public array $line,
    ) {
        if (count($this->line) < 1) {
            throw new InvoiceModelException('Lines: line requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            mergedItemIndicator: $reader->one(Ns::DATA, 'mergedItemIndicator', Value::bool(...)),
            line: $reader->many(Ns::DATA, 'line', Line::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'mergedItemIndicator', Value::fromBool($this->mergedItemIndicator));
        $writer->children(Ns::DATA, 'line', $this->line);
    }
}
