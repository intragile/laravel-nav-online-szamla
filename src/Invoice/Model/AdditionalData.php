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
 * Type for additional data description.
 *
 * Generated from AdditionalDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class AdditionalData implements ModelElement
{
    /**
     * @param  string  $dataName  Unique identification of the data field.
     * @param  string  $dataDescription  Description of content of the data field.
     * @param  string  $dataValue  Value of the data.
     */
    public function __construct(
        public string $dataName,
        public string $dataDescription,
        public string $dataValue,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            dataName: $reader->one(Ns::DATA, 'dataName', Value::string(...)),
            dataDescription: $reader->one(Ns::DATA, 'dataDescription', Value::string(...)),
            dataValue: $reader->one(Ns::DATA, 'dataValue', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'dataName', $this->dataName);
        $writer->text(Ns::DATA, 'dataDescription', $this->dataDescription);
        $writer->text(Ns::DATA, 'dataValue', $this->dataValue);
    }
}
