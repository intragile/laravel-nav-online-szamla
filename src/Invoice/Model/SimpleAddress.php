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
 * Simple address type.
 *
 * Generated from SimpleAddressType (invoiceBase.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class SimpleAddress implements ModelElement
{
    /**
     * @param  string  $countryCode  ISO 3166 alpha-2 country code.
     * @param  string  $postalCode  ZIP code (If can not be interpreted, need to be filled "0000").
     * @param  string  $city  Settlement.
     * @param  string  $additionalAddressDetail  Further address data (name and type of public place, house number, floor, door, lot number, etc.).
     * @param  string|null  $region  ISO 3166 alpha-2 province code (if appropriate in a given country).
     */
    public function __construct(
        public string $countryCode,
        public string $postalCode,
        public string $city,
        public string $additionalAddressDetail,
        public ?string $region = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            countryCode: $reader->one(Ns::BASE, 'countryCode', Value::string(...)),
            region: $reader->optional(Ns::BASE, 'region', Value::string(...)),
            postalCode: $reader->one(Ns::BASE, 'postalCode', Value::string(...)),
            city: $reader->one(Ns::BASE, 'city', Value::string(...)),
            additionalAddressDetail: $reader->one(Ns::BASE, 'additionalAddressDetail', Value::string(...)),
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
        $writer->text(Ns::BASE, 'additionalAddressDetail', $this->additionalAddressDetail);
    }
}
