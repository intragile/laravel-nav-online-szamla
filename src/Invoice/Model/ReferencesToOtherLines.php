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
 * References to connected items if it is necessary according to VAT law.
 *
 * Generated from ReferencesToOtherLinesType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class ReferencesToOtherLines implements ModelElement
{
    /**
     * @param  list<int>  $referenceToOtherLine  References to connected items if it is necessary according to VAT law.
     */
    public function __construct(
        public array $referenceToOtherLine,
    ) {
        if (count($this->referenceToOtherLine) < 1) {
            throw new InvoiceModelException('ReferencesToOtherLines: referenceToOtherLine requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            referenceToOtherLine: $reader->many(Ns::DATA, 'referenceToOtherLine', Value::int(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->texts(Ns::DATA, 'referenceToOtherLine', array_map(static fn (int $v): string => (string) $v, $this->referenceToOtherLine));
    }
}
