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
 * Data of mismatching tax base and levied tax.
 *
 * Generated from VatAmountMismatchType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class VatAmountMismatch implements ModelElement
{
    /**
     * @param  Decimal  $vatRate  VAT rate, VAT content.
     * @param  string  $case  Case notation with code.
     */
    public function __construct(
        public Decimal $vatRate,
        public string $case,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            vatRate: $reader->one(Ns::DATA, 'vatRate', Value::decimal(...)),
            case: $reader->one(Ns::DATA, 'case', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'vatRate', $this->vatRate->value);
        $writer->text(Ns::DATA, 'case', $this->case);
    }
}
