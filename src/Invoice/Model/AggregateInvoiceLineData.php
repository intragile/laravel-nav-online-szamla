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
 * Field type including aggregate invoice special data.
 *
 * Generated from AggregateInvoiceLineDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class AggregateInvoiceLineData implements ModelElement
{
    /**
     * @param  DateTimeImmutable  $lineDeliveryDate  Delivery date of the given item in the case of an aggregate invoice.
     * @param  Decimal|null  $lineExchangeRate  The exchange rate applied to the item, pertaining to 1 (one) unit. This should be filled in only if an aggregate invoice in foreign currency is issued.
     */
    public function __construct(
        public DateTimeImmutable $lineDeliveryDate,
        public ?Decimal $lineExchangeRate = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            lineExchangeRate: $reader->optional(Ns::DATA, 'lineExchangeRate', Value::decimal(...)),
            lineDeliveryDate: $reader->one(Ns::DATA, 'lineDeliveryDate', Value::date(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'lineExchangeRate', $this->lineExchangeRate?->value);
        $writer->text(Ns::DATA, 'lineDeliveryDate', Value::fromDate($this->lineDeliveryDate));
    }
}
