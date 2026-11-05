<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Support\Decimal;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Aircraft.
 *
 * Generated from AircraftType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class Aircraft implements ModelElement
{
    /**
     * @param  Decimal  $takeOffWeight  Take-off weight in kilogram.
     * @param  bool  $airCargo  The value is true if the means of transport is exempt from VAT as per section 259 [25] (c).
     * @param  Decimal  $operationHours  Number of aviated hours.
     */
    public function __construct(
        public Decimal $takeOffWeight,
        public bool $airCargo,
        public Decimal $operationHours,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            takeOffWeight: $reader->one(Ns::DATA, 'takeOffWeight', Value::decimal(...)),
            airCargo: $reader->one(Ns::DATA, 'airCargo', Value::bool(...)),
            operationHours: $reader->one(Ns::DATA, 'operationHours', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'takeOffWeight', $this->takeOffWeight->value);
        $writer->text(Ns::DATA, 'airCargo', Value::fromBool($this->airCargo));
        $writer->text(Ns::DATA, 'operationHours', $this->operationHours->value);
    }
}
