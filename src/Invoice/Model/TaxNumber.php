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
 * Tax number type.
 *
 * Generated from TaxNumberType (invoiceBase.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class TaxNumber implements ModelElement
{
    /**
     * @param  string  $taxpayerId  Core tax number of the taxable person. In case of group taxation arrangement the group identification number.
     * @param  string|null  $vatCode  VAT code to indicate taxation type of the taxpayer. One digit.
     * @param  string|null  $countyCode  County code, two digits.
     */
    public function __construct(
        public string $taxpayerId,
        public ?string $vatCode = null,
        public ?string $countyCode = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            taxpayerId: $reader->one(Ns::BASE, 'taxpayerId', Value::string(...)),
            vatCode: $reader->optional(Ns::BASE, 'vatCode', Value::string(...)),
            countyCode: $reader->optional(Ns::BASE, 'countyCode', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::BASE, 'taxpayerId', $this->taxpayerId);
        $writer->text(Ns::BASE, 'vatCode', $this->vatCode);
        $writer->text(Ns::BASE, 'countyCode', $this->countyCode);
    }
}
