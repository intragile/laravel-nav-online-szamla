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
 * Advance related data.
 *
 * Generated from AdvanceDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class AdvanceData implements ModelElement
{
    /**
     * @param  bool  $advanceIndicator  The value is true if the invoice item is a kind of advance charge.
     * @param  AdvancePaymentData|null  $advancePaymentData  Advance payment related data.
     */
    public function __construct(
        public bool $advanceIndicator,
        public ?AdvancePaymentData $advancePaymentData = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            advanceIndicator: $reader->one(Ns::DATA, 'advanceIndicator', Value::bool(...)),
            advancePaymentData: $reader->optional(Ns::DATA, 'advancePaymentData', AdvancePaymentData::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'advanceIndicator', Value::fromBool($this->advanceIndicator));
        $writer->child(Ns::DATA, 'advancePaymentData', $this->advancePaymentData);
    }
}
