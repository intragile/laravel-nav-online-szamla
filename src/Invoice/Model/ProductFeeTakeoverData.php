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
 * Data in connection with takeover of environmental protection product fee.
 *
 * Generated from ProductFeeTakeoverDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class ProductFeeTakeoverData implements ModelElement
{
    /**
     * @param  Takeover  $takeoverReason  Direction and legal base of takeover.
     * @param  Decimal|null  $takeoverAmount  Amount in Hungarian forints of the product fee taken over if the purchaser takes over the supplier’s product fee liability.
     */
    public function __construct(
        public Takeover $takeoverReason,
        public ?Decimal $takeoverAmount = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            takeoverReason: $reader->one(Ns::DATA, 'takeoverReason', static fn (DOMElement $e): Takeover => Takeover::from(Value::string($e))),
            takeoverAmount: $reader->optional(Ns::DATA, 'takeoverAmount', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'takeoverReason', $this->takeoverReason->value);
        $writer->text(Ns::DATA, 'takeoverAmount', $this->takeoverAmount?->value);
    }
}
