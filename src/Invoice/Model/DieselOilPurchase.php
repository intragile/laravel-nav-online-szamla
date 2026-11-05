<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DateTimeImmutable;
use DOMElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Support\Decimal;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Data of gas oil acquisition after taxation – point a), paragraph (1) of Section 75 of the NGM Decree No. 45/2016. (XI. 29.).
 *
 * Generated from DieselOilPurchaseType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class DieselOilPurchase implements ModelElement
{
    /**
     * @param  SimpleAddress  $purchaseLocation  Place of purchase of the gas oil.
     * @param  DateTimeImmutable  $purchaseDate  Date of purchase of gas oil.
     * @param  string  $vehicleRegistrationNumber  Registration number of vehicle (letters and numbers only).
     * @param  Decimal|null  $dieselOilQuantity  Fordítandó helyett: Quantity of diesel oil used for contract work and machinery hire service in liter – Act LXVIII of 2016 on Excise Tax section 117 (2).
     */
    public function __construct(
        public SimpleAddress $purchaseLocation,
        public DateTimeImmutable $purchaseDate,
        public string $vehicleRegistrationNumber,
        public ?Decimal $dieselOilQuantity = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            purchaseLocation: $reader->one(Ns::DATA, 'purchaseLocation', SimpleAddress::fromXml(...)),
            purchaseDate: $reader->one(Ns::DATA, 'purchaseDate', Value::date(...)),
            vehicleRegistrationNumber: $reader->one(Ns::DATA, 'vehicleRegistrationNumber', Value::string(...)),
            dieselOilQuantity: $reader->optional(Ns::DATA, 'dieselOilQuantity', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::DATA, 'purchaseLocation', $this->purchaseLocation);
        $writer->text(Ns::DATA, 'purchaseDate', Value::fromDate($this->purchaseDate));
        $writer->text(Ns::DATA, 'vehicleRegistrationNumber', $this->vehicleRegistrationNumber);
        $writer->text(Ns::DATA, 'dieselOilQuantity', $this->dieselOilQuantity?->value);
    }
}
