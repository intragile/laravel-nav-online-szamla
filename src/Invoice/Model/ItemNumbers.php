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
 * Item numbers.
 *
 * Generated from ItemNumbersType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class ItemNumbers implements ModelElement
{
    /**
     * @param  list<string>  $itemNumber  Item number.
     */
    public function __construct(
        public array $itemNumber,
    ) {
        if (count($this->itemNumber) < 1) {
            throw new InvoiceModelException('ItemNumbers: itemNumber requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            itemNumber: $reader->many(Ns::DATA, 'itemNumber', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->texts(Ns::DATA, 'itemNumber', $this->itemNumber);
    }
}
