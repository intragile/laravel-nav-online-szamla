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
 * Shipping dates.
 *
 * Generated from ShippingDatesType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class ShippingDates implements ModelElement
{
    /**
     * @param  list<string>  $shippingDate  Shipping date.
     */
    public function __construct(
        public array $shippingDate,
    ) {
        if (count($this->shippingDate) < 1) {
            throw new InvoiceModelException('ShippingDates: shippingDate requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            shippingDate: $reader->many(Ns::DATA, 'shippingDate', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->texts(Ns::DATA, 'shippingDate', $this->shippingDate);
    }
}
