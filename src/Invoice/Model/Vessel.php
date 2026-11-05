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
 * Data of vessel.
 *
 * Generated from VesselType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class Vessel implements ModelElement
{
    /**
     * @param  Decimal  $length  Length of hull in metre.
     * @param  bool  $activityReferred  The value is true if the means of transport is exempt from VAT as per section 259 [25] (b).
     * @param  Decimal  $sailedHours  Number of sailed hours.
     */
    public function __construct(
        public Decimal $length,
        public bool $activityReferred,
        public Decimal $sailedHours,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            length: $reader->one(Ns::DATA, 'length', Value::decimal(...)),
            activityReferred: $reader->one(Ns::DATA, 'activityReferred', Value::bool(...)),
            sailedHours: $reader->one(Ns::DATA, 'sailedHours', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'length', $this->length->value);
        $writer->text(Ns::DATA, 'activityReferred', Value::fromBool($this->activityReferred));
        $writer->text(Ns::DATA, 'sailedHours', $this->sailedHours->value);
    }
}
