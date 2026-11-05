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
 * Discount data.
 *
 * Generated from DiscountDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class DiscountData implements ModelElement
{
    /**
     * @param  string|null  $discountDescription  Description of the discount.
     * @param  Decimal|null  $discountValue  Total amount of discount per item expressed in the currency of the invoice if not included in the unit price.
     * @param  Decimal|null  $discountRate  Rate of discount per item expressed in the currency of the invoice if not included in the unit price.
     */
    public function __construct(
        public ?string $discountDescription = null,
        public ?Decimal $discountValue = null,
        public ?Decimal $discountRate = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            discountDescription: $reader->optional(Ns::DATA, 'discountDescription', Value::string(...)),
            discountValue: $reader->optional(Ns::DATA, 'discountValue', Value::decimal(...)),
            discountRate: $reader->optional(Ns::DATA, 'discountRate', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'discountDescription', $this->discountDescription);
        $writer->text(Ns::DATA, 'discountValue', $this->discountValue?->value);
        $writer->text(Ns::DATA, 'discountRate', $this->discountRate?->value);
    }
}
