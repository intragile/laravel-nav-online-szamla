<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Format of address that includes either a simple or a detailed address.
 *
 * Generated from AddressType (invoiceBase.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class Address implements ModelElement
{
    /**
     * @param  SimpleAddress|null  $simpleAddress  Simple address.
     * @param  DetailedAddress|null  $detailedAddress  Detailed address.
     */
    public function __construct(
        public ?SimpleAddress $simpleAddress = null,
        public ?DetailedAddress $detailedAddress = null,
    ) {
        if ((($this->simpleAddress !== null ? 1 : 0) + ($this->detailedAddress !== null ? 1 : 0)) !== 1) {
            throw new InvoiceModelException('Address: Exactly one of simpleAddress, detailedAddress must be set.');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            simpleAddress: $reader->optional(Ns::BASE, 'simpleAddress', SimpleAddress::fromXml(...)),
            detailedAddress: $reader->optional(Ns::BASE, 'detailedAddress', DetailedAddress::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::BASE, 'simpleAddress', $this->simpleAddress);
        $writer->child(Ns::BASE, 'detailedAddress', $this->detailedAddress);
    }
}
