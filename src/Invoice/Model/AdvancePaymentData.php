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
 * Advance payment related data.
 *
 * Generated from AdvancePaymentDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class AdvancePaymentData implements ModelElement
{
    /**
     * @param  string  $advanceOriginalInvoice  Invoice number containing the advance payment.
     * @param  DateTimeImmutable  $advancePaymentDate  Payment date of the advance.
     * @param  Decimal  $advanceExchangeRate  Applied exchange rate of the advance.
     */
    public function __construct(
        public string $advanceOriginalInvoice,
        public DateTimeImmutable $advancePaymentDate,
        public Decimal $advanceExchangeRate,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            advanceOriginalInvoice: $reader->one(Ns::DATA, 'advanceOriginalInvoice', Value::string(...)),
            advancePaymentDate: $reader->one(Ns::DATA, 'advancePaymentDate', Value::date(...)),
            advanceExchangeRate: $reader->one(Ns::DATA, 'advanceExchangeRate', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'advanceOriginalInvoice', $this->advanceOriginalInvoice);
        $writer->text(Ns::DATA, 'advancePaymentDate', Value::fromDate($this->advancePaymentDate));
        $writer->text(Ns::DATA, 'advanceExchangeRate', $this->advanceExchangeRate->value);
    }
}
