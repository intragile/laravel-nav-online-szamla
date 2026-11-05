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
 * Detailed address data.
 *
 * Generated from DetailedAddressType (invoiceBase.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class DetailedAddress implements ModelElement
{
    /**
     * @param  string  $countryCode  ISO 3166 alpha-2 country code.
     * @param  string  $postalCode  ZIP code (If can not be interpreted, need to be filled "0000").
     * @param  string  $city  Settlement.
     * @param  string  $streetName  Name of public place.
     * @param  string  $publicPlaceCategory  Category of public place.
     * @param  string|null  $region  ISO 3166 alpha-2 province code (if appropriate in a given country).
     * @param  string|null  $number  House number.
     * @param  string|null  $building  Building.
     * @param  string|null  $staircase  Staircase.
     * @param  string|null  $floor  Floor.
     * @param  string|null  $door  Door number.
     * @param  string|null  $lotNumber  Lot number.
     */
    public function __construct(
        public string $countryCode,
        public string $postalCode,
        public string $city,
        public string $streetName,
        public string $publicPlaceCategory,
        public ?string $region = null,
        public ?string $number = null,
        public ?string $building = null,
        public ?string $staircase = null,
        public ?string $floor = null,
        public ?string $door = null,
        public ?string $lotNumber = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            countryCode: $reader->one(Ns::BASE, 'countryCode', Value::string(...)),
            region: $reader->optional(Ns::BASE, 'region', Value::string(...)),
            postalCode: $reader->one(Ns::BASE, 'postalCode', Value::string(...)),
            city: $reader->one(Ns::BASE, 'city', Value::string(...)),
            streetName: $reader->one(Ns::BASE, 'streetName', Value::string(...)),
            publicPlaceCategory: $reader->one(Ns::BASE, 'publicPlaceCategory', Value::string(...)),
            number: $reader->optional(Ns::BASE, 'number', Value::string(...)),
            building: $reader->optional(Ns::BASE, 'building', Value::string(...)),
            staircase: $reader->optional(Ns::BASE, 'staircase', Value::string(...)),
            floor: $reader->optional(Ns::BASE, 'floor', Value::string(...)),
            door: $reader->optional(Ns::BASE, 'door', Value::string(...)),
            lotNumber: $reader->optional(Ns::BASE, 'lotNumber', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::BASE, 'countryCode', $this->countryCode);
        $writer->text(Ns::BASE, 'region', $this->region);
        $writer->text(Ns::BASE, 'postalCode', $this->postalCode);
        $writer->text(Ns::BASE, 'city', $this->city);
        $writer->text(Ns::BASE, 'streetName', $this->streetName);
        $writer->text(Ns::BASE, 'publicPlaceCategory', $this->publicPlaceCategory);
        $writer->text(Ns::BASE, 'number', $this->number);
        $writer->text(Ns::BASE, 'building', $this->building);
        $writer->text(Ns::BASE, 'staircase', $this->staircase);
        $writer->text(Ns::BASE, 'floor', $this->floor);
        $writer->text(Ns::BASE, 'door', $this->door);
        $writer->text(Ns::BASE, 'lotNumber', $this->lotNumber);
    }
}
