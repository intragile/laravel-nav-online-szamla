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
 * Material numbers.
 *
 * Generated from MaterialNumbersType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class MaterialNumbers implements ModelElement
{
    /**
     * @param  list<string>  $materialNumber  Material number.
     */
    public function __construct(
        public array $materialNumber,
    ) {
        if (count($this->materialNumber) < 1) {
            throw new InvoiceModelException('MaterialNumbers: materialNumber requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            materialNumber: $reader->many(Ns::DATA, 'materialNumber', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->texts(Ns::DATA, 'materialNumber', $this->materialNumber);
    }
}
