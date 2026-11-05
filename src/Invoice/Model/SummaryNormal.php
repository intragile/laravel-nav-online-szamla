<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Support\Decimal;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Calculation of invoice totals (not simplified invoice).
 *
 * Generated from SummaryNormalType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class SummaryNormal implements ModelElement
{
    /**
     * @param  list<SummaryByVatRate>  $summaryByVatRate  Calculation of invoice totals per VAT rates.
     * @param  Decimal  $invoiceNetAmount  Net amount of the invoice expressed in the currency of the invoice.
     * @param  Decimal  $invoiceNetAmountHUF  Net amount of the invoice expressed in HUF.
     * @param  Decimal  $invoiceVatAmount  VAT amount of the invoice expressed in the currency of the invoice.
     * @param  Decimal  $invoiceVatAmountHUF  VAT amount of the invoice expressed in HUF.
     */
    public function __construct(
        public array $summaryByVatRate,
        public Decimal $invoiceNetAmount,
        public Decimal $invoiceNetAmountHUF,
        public Decimal $invoiceVatAmount,
        public Decimal $invoiceVatAmountHUF,
    ) {
        if (count($this->summaryByVatRate) < 1) {
            throw new InvoiceModelException('SummaryNormal: summaryByVatRate requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            summaryByVatRate: $reader->many(Ns::DATA, 'summaryByVatRate', SummaryByVatRate::fromXml(...)),
            invoiceNetAmount: $reader->one(Ns::DATA, 'invoiceNetAmount', Value::decimal(...)),
            invoiceNetAmountHUF: $reader->one(Ns::DATA, 'invoiceNetAmountHUF', Value::decimal(...)),
            invoiceVatAmount: $reader->one(Ns::DATA, 'invoiceVatAmount', Value::decimal(...)),
            invoiceVatAmountHUF: $reader->one(Ns::DATA, 'invoiceVatAmountHUF', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->children(Ns::DATA, 'summaryByVatRate', $this->summaryByVatRate);
        $writer->text(Ns::DATA, 'invoiceNetAmount', $this->invoiceNetAmount->value);
        $writer->text(Ns::DATA, 'invoiceNetAmountHUF', $this->invoiceNetAmountHUF->value);
        $writer->text(Ns::DATA, 'invoiceVatAmount', $this->invoiceVatAmount->value);
        $writer->text(Ns::DATA, 'invoiceVatAmountHUF', $this->invoiceVatAmountHUF->value);
    }
}
