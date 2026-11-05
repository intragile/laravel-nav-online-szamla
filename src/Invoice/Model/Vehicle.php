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
 * Other data in relation to motorised land vehicle.
 *
 * Generated from VehicleType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class Vehicle implements ModelElement
{
    /**
     * @param  Decimal  $engineCapacity  Engine capacity in cubic centimetre.
     * @param  Decimal  $enginePower  Engine power in kW.
     * @param  Decimal  $kms  Travelled distance in km.
     */
    public function __construct(
        public Decimal $engineCapacity,
        public Decimal $enginePower,
        public Decimal $kms,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            engineCapacity: $reader->one(Ns::DATA, 'engineCapacity', Value::decimal(...)),
            enginePower: $reader->one(Ns::DATA, 'enginePower', Value::decimal(...)),
            kms: $reader->one(Ns::DATA, 'kms', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'engineCapacity', $this->engineCapacity->value);
        $writer->text(Ns::DATA, 'enginePower', $this->enginePower->value);
        $writer->text(Ns::DATA, 'kms', $this->kms->value);
    }
}
