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
 * Order numbers.
 *
 * Generated from OrderNumbersType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class OrderNumbers implements ModelElement
{
    /**
     * @param  list<string>  $orderNumber  Order number.
     */
    public function __construct(
        public array $orderNumber,
    ) {
        if (count($this->orderNumber) < 1) {
            throw new InvoiceModelException('OrderNumbers: orderNumber requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            orderNumber: $reader->many(Ns::DATA, 'orderNumber', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->texts(Ns::DATA, 'orderNumber', $this->orderNumber);
    }
}
