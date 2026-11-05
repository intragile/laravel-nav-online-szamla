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
 * Summary of product charges.
 *
 * Generated from ProductFeeSummaryType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class ProductFeeSummary implements ModelElement
{
    /**
     * @param  ProductFeeOperation  $productFeeOperation  Indicating whether the the product fee summary concerns refund or deposit.
     * @param  list<ProductFeeData>  $productFeeData  Product charges data.
     * @param  Decimal  $productChargeSum  Aggregate product charges.
     * @param  PaymentEvidenceDocumentData|null  $paymentEvidenceDocumentData  Data of the document verifying the declaration submitted on the product fee according to the Paragraph (3), Section 13 and the Paragraph (3) Section 25 of the Act LXXXV of 2011.
     */
    public function __construct(
        public ProductFeeOperation $productFeeOperation,
        public array $productFeeData,
        public Decimal $productChargeSum,
        public ?PaymentEvidenceDocumentData $paymentEvidenceDocumentData = null,
    ) {
        if (count($this->productFeeData) < 1) {
            throw new InvoiceModelException('ProductFeeSummary: productFeeData requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            productFeeOperation: $reader->one(Ns::DATA, 'productFeeOperation', static fn (DOMElement $e): ProductFeeOperation => ProductFeeOperation::from(Value::string($e))),
            productFeeData: $reader->many(Ns::DATA, 'productFeeData', ProductFeeData::fromXml(...)),
            productChargeSum: $reader->one(Ns::DATA, 'productChargeSum', Value::decimal(...)),
            paymentEvidenceDocumentData: $reader->optional(Ns::DATA, 'paymentEvidenceDocumentData', PaymentEvidenceDocumentData::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'productFeeOperation', $this->productFeeOperation->value);
        $writer->children(Ns::DATA, 'productFeeData', $this->productFeeData);
        $writer->text(Ns::DATA, 'productChargeSum', $this->productChargeSum->value);
        $writer->child(Ns::DATA, 'paymentEvidenceDocumentData', $this->paymentEvidenceDocumentData);
    }
}
