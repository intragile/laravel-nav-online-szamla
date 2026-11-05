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
 * Project numbers.
 *
 * Generated from ProjectNumbersType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class ProjectNumbers implements ModelElement
{
    /**
     * @param  list<string>  $projectNumber  Project number.
     */
    public function __construct(
        public array $projectNumber,
    ) {
        if (count($this->projectNumber) < 1) {
            throw new InvoiceModelException('ProjectNumbers: projectNumber requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            projectNumber: $reader->many(Ns::DATA, 'projectNumber', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->texts(Ns::DATA, 'projectNumber', $this->projectNumber);
    }
}
