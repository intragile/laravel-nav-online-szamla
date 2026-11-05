<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DateTimeImmutable;
use DOMElement;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceAppearance;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceCategory;
use IntrAgile\NavOnlineSzamla\Enums\PaymentMethod;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Support\Decimal;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Invoice detail data.
 *
 * Generated from InvoiceDetailType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class InvoiceDetail implements ModelElement
{
    /**
     * @param  InvoiceCategory  $invoiceCategory  Type of invoice. In case of modification document the type of original invoice.
     * @param  DateTimeImmutable  $invoiceDeliveryDate  Delivery date (if this field does not exist on the invoice, the date of the invoice should be considered as such) - section 169 (g) of the VAT law.
     * @param  string  $currencyCode  ISO 4217 currency code on the invoice.
     * @param  Decimal  $exchangeRate  In case any currency is used other than HUF, the applied exchange rate should be mentioned: 1 unit of the foreign currency expressed in HUF.
     * @param  InvoiceAppearance  $invoiceAppearance  Form of appearance of the invoice or modification document.
     * @param  DateTimeImmutable|null  $invoiceDeliveryPeriodStart  The first day of the delivery, if the invoice delivery is a period.
     * @param  DateTimeImmutable|null  $invoiceDeliveryPeriodEnd  The last day of the delivery, if the invoice delivery is a period.
     * @param  DateTimeImmutable|null  $invoiceAccountingDeliveryDate  Date of accounting accomplishment. In the event of a period, the last day of the period.
     * @param  bool|null  $periodicalSettlement  Indicates where by agreement of the parties it gives rise to successive statements of account or successive payments relating to the supply of goods, or the supply of services, or if the consideration agreed upon for such goods and/or services applies to specific periods.
     * @param  bool|null  $smallBusinessIndicator  Marking of low tax-bracket enterprise.
     * @param  bool|null  $utilitySettlementIndicator  Marking the fact of utility settlement invoice (invoice according to Act CLXXXVIII of 2013).
     * @param  bool|null  $selfBillingIndicator  Marking the fact of self-billing (in the case of self-billing the value is true).
     * @param  PaymentMethod|null  $paymentMethod  Method of payment.
     * @param  DateTimeImmutable|null  $paymentDate  Deadline for payment.
     * @param  bool|null  $cashAccountingIndicator  Marking the fact of cash accounting if this is indicated on the invoice - section 169 (h) of the VAT law. The value is true in case of cash accounting.
     * @param  ConventionalInvoiceInfo|null  $conventionalInvoiceInfo  Other conventionally named data to assist in invoice processing.
     * @param  list<AdditionalData>  $additionalInvoiceData  Other data in relation to the invoice.
     */
    public function __construct(
        public InvoiceCategory $invoiceCategory,
        public DateTimeImmutable $invoiceDeliveryDate,
        public string $currencyCode,
        public Decimal $exchangeRate,
        public InvoiceAppearance $invoiceAppearance,
        public ?DateTimeImmutable $invoiceDeliveryPeriodStart = null,
        public ?DateTimeImmutable $invoiceDeliveryPeriodEnd = null,
        public ?DateTimeImmutable $invoiceAccountingDeliveryDate = null,
        public ?bool $periodicalSettlement = null,
        public ?bool $smallBusinessIndicator = null,
        public ?bool $utilitySettlementIndicator = null,
        public ?bool $selfBillingIndicator = null,
        public ?PaymentMethod $paymentMethod = null,
        public ?DateTimeImmutable $paymentDate = null,
        public ?bool $cashAccountingIndicator = null,
        public ?ConventionalInvoiceInfo $conventionalInvoiceInfo = null,
        public array $additionalInvoiceData = [],
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            invoiceCategory: $reader->one(Ns::DATA, 'invoiceCategory', static fn (DOMElement $e): InvoiceCategory => InvoiceCategory::from(Value::string($e))),
            invoiceDeliveryDate: $reader->one(Ns::DATA, 'invoiceDeliveryDate', Value::date(...)),
            invoiceDeliveryPeriodStart: $reader->optional(Ns::DATA, 'invoiceDeliveryPeriodStart', Value::date(...)),
            invoiceDeliveryPeriodEnd: $reader->optional(Ns::DATA, 'invoiceDeliveryPeriodEnd', Value::date(...)),
            invoiceAccountingDeliveryDate: $reader->optional(Ns::DATA, 'invoiceAccountingDeliveryDate', Value::date(...)),
            periodicalSettlement: $reader->optional(Ns::DATA, 'periodicalSettlement', Value::bool(...)),
            smallBusinessIndicator: $reader->optional(Ns::DATA, 'smallBusinessIndicator', Value::bool(...)),
            currencyCode: $reader->one(Ns::DATA, 'currencyCode', Value::string(...)),
            exchangeRate: $reader->one(Ns::DATA, 'exchangeRate', Value::decimal(...)),
            utilitySettlementIndicator: $reader->optional(Ns::DATA, 'utilitySettlementIndicator', Value::bool(...)),
            selfBillingIndicator: $reader->optional(Ns::DATA, 'selfBillingIndicator', Value::bool(...)),
            paymentMethod: $reader->optional(Ns::DATA, 'paymentMethod', static fn (DOMElement $e): PaymentMethod => PaymentMethod::from(Value::string($e))),
            paymentDate: $reader->optional(Ns::DATA, 'paymentDate', Value::date(...)),
            cashAccountingIndicator: $reader->optional(Ns::DATA, 'cashAccountingIndicator', Value::bool(...)),
            invoiceAppearance: $reader->one(Ns::DATA, 'invoiceAppearance', static fn (DOMElement $e): InvoiceAppearance => InvoiceAppearance::from(Value::string($e))),
            conventionalInvoiceInfo: $reader->optional(Ns::DATA, 'conventionalInvoiceInfo', ConventionalInvoiceInfo::fromXml(...)),
            additionalInvoiceData: $reader->many(Ns::DATA, 'additionalInvoiceData', AdditionalData::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'invoiceCategory', $this->invoiceCategory->value);
        $writer->text(Ns::DATA, 'invoiceDeliveryDate', Value::fromDate($this->invoiceDeliveryDate));
        $writer->text(Ns::DATA, 'invoiceDeliveryPeriodStart', Value::fromDate($this->invoiceDeliveryPeriodStart));
        $writer->text(Ns::DATA, 'invoiceDeliveryPeriodEnd', Value::fromDate($this->invoiceDeliveryPeriodEnd));
        $writer->text(Ns::DATA, 'invoiceAccountingDeliveryDate', Value::fromDate($this->invoiceAccountingDeliveryDate));
        $writer->text(Ns::DATA, 'periodicalSettlement', Value::fromBool($this->periodicalSettlement));
        $writer->text(Ns::DATA, 'smallBusinessIndicator', Value::fromBool($this->smallBusinessIndicator));
        $writer->text(Ns::DATA, 'currencyCode', $this->currencyCode);
        $writer->text(Ns::DATA, 'exchangeRate', $this->exchangeRate->value);
        $writer->text(Ns::DATA, 'utilitySettlementIndicator', Value::fromBool($this->utilitySettlementIndicator));
        $writer->text(Ns::DATA, 'selfBillingIndicator', Value::fromBool($this->selfBillingIndicator));
        $writer->text(Ns::DATA, 'paymentMethod', $this->paymentMethod?->value);
        $writer->text(Ns::DATA, 'paymentDate', Value::fromDate($this->paymentDate));
        $writer->text(Ns::DATA, 'cashAccountingIndicator', Value::fromBool($this->cashAccountingIndicator));
        $writer->text(Ns::DATA, 'invoiceAppearance', $this->invoiceAppearance->value);
        $writer->child(Ns::DATA, 'conventionalInvoiceInfo', $this->conventionalInvoiceInfo);
        $writer->children(Ns::DATA, 'additionalInvoiceData', $this->additionalInvoiceData);
    }
}
