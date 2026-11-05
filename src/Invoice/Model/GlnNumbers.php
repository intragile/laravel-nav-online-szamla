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
 * Global location numbers.
 *
 * Generated from GlnNumbersType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class GlnNumbers implements ModelElement
{
    /**
     * @param  list<string>  $glnNumber  Global location number.
     */
    public function __construct(
        public array $glnNumber,
    ) {
        if (count($this->glnNumber) < 1) {
            throw new InvoiceModelException('GlnNumbers: glnNumber requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            glnNumber: $reader->many(Ns::DATA, 'glnNumber', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->texts(Ns::DATA, 'glnNumber', $this->glnNumber);
    }
}
