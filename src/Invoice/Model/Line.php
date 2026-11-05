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
 * Field type including data of invoice items (product or service).
 *
 * Generated from LineType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class Line implements ModelElement
{
    /**
     * @param  int  $lineNumber  Sequential number of the item.
     * @param  bool  $lineExpressionIndicator  The value is true if the unit of measure of the invoice item is expressible in natural unit.
     * @param  LineModificationReference|null  $lineModificationReference  Marking the goal of modification of the line (in the case of data supply about changes/updates only).
     * @param  ReferencesToOtherLines|null  $referencesToOtherLines  References to connected items if it is necessary according to VAT law.
     * @param  AdvanceData|null  $advanceData  Advance related data.
     * @param  ProductCodes|null  $productCodes  Product codes.
     * @param  LineNatureIndicator|null  $lineNatureIndicator  Indication of the nature of the supply of goods or services on a given line.
     * @param  string|null  $lineDescription  Name / description of the product or service.
     * @param  Decimal|null  $quantity  Quantity.
     * @param  UnitOfMeasure|null  $unitOfMeasure  Canonical representation of the unit of measure of the invoice, according to the interface specification.
     * @param  string|null  $unitOfMeasureOwn  Literal unit of measure of the invoice.
     * @param  Decimal|null  $unitPrice  Unit price expressed in the currency of the invoice In the event of simplified invoices gross unit price, in other cases net unit price.
     * @param  Decimal|null  $unitPriceHUF  Unit price expressed in HUF.
     * @param  DiscountData|null  $lineDiscountData  Discount data in relation to the item.
     * @param  LineAmountsNormal|null  $lineAmountsNormal  Item value data to be completed in case of normal (not simplified, but including aggregated) invoice.
     * @param  LineAmountsSimplified|null  $lineAmountsSimplified  Item value data to be completed in case of simplified invoice.
     * @param  bool|null  $intermediatedService  The value is true if the item is an intermediated service - paragraph (4) 1 of the Article 3 of Accounting Act.
     * @param  AggregateInvoiceLineData|null  $aggregateInvoiceLineData  Aggregate invoice data.
     * @param  NewTransportMean|null  $newTransportMean  Supply of new means of transport - section 89 § and 169 (o) of the VAT law.
     * @param  bool|null  $depositIndicator  The value is true if the item is bottle/container deposit.
     * @param  bool|null  $obligatedForProductFee  The value is true if the item is liable to product fee.
     * @param  Decimal|null  $GPCExcise  Excise duty on natural gas, electricity and coal in Hungarian forints – paragraph (2), Section 118 of the Act on Excise Duties.
     * @param  DieselOilPurchase|null  $dieselOilPurchase  Data of gas oil acquisition after taxation – point a), paragraph (1) of Section 75 of the NGM Decree No. 45/2016. (XI. 29.).
     * @param  bool|null  $netaDeclaration  Value is true, if the taxable person is liable for tax obligation determined in the Act on Public Health Product Tax (Neta tv.). Paragraph (2), Section 3 of the Act CIII of 2011.
     * @param  ProductFeeClause|null  $productFeeClause  Clauses according to the Act LXXXV of 2011 on Environmental Protection Product Fee (related to the item).
     * @param  list<ProductFeeData>  $lineProductFeeContent  Data on the content of the line's product charge.
     * @param  ConventionalInvoiceInfo|null  $conventionalLineInfo  Other conventionally named data to assist in invoice processing.
     * @param  list<AdditionalData>  $additionalLineData  Other data in relation to the product / service item.
     */
    public function __construct(
        public int $lineNumber,
        public bool $lineExpressionIndicator,
        public ?LineModificationReference $lineModificationReference = null,
        public ?ReferencesToOtherLines $referencesToOtherLines = null,
        public ?AdvanceData $advanceData = null,
        public ?ProductCodes $productCodes = null,
        public ?LineNatureIndicator $lineNatureIndicator = null,
        public ?string $lineDescription = null,
        public ?Decimal $quantity = null,
        public ?UnitOfMeasure $unitOfMeasure = null,
        public ?string $unitOfMeasureOwn = null,
        public ?Decimal $unitPrice = null,
        public ?Decimal $unitPriceHUF = null,
        public ?DiscountData $lineDiscountData = null,
        public ?LineAmountsNormal $lineAmountsNormal = null,
        public ?LineAmountsSimplified $lineAmountsSimplified = null,
        public ?bool $intermediatedService = null,
        public ?AggregateInvoiceLineData $aggregateInvoiceLineData = null,
        public ?NewTransportMean $newTransportMean = null,
        public ?bool $depositIndicator = null,
        public ?bool $obligatedForProductFee = null,
        public ?Decimal $GPCExcise = null,
        public ?DieselOilPurchase $dieselOilPurchase = null,
        public ?bool $netaDeclaration = null,
        public ?ProductFeeClause $productFeeClause = null,
        public array $lineProductFeeContent = [],
        public ?ConventionalInvoiceInfo $conventionalLineInfo = null,
        public array $additionalLineData = [],
    ) {
        if ((($this->lineAmountsNormal !== null ? 1 : 0) + ($this->lineAmountsSimplified !== null ? 1 : 0)) > 1) {
            throw new InvoiceModelException('Line: At most one of lineAmountsNormal, lineAmountsSimplified may be set.');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            lineNumber: $reader->one(Ns::DATA, 'lineNumber', Value::int(...)),
            lineModificationReference: $reader->optional(Ns::DATA, 'lineModificationReference', LineModificationReference::fromXml(...)),
            referencesToOtherLines: $reader->optional(Ns::DATA, 'referencesToOtherLines', ReferencesToOtherLines::fromXml(...)),
            advanceData: $reader->optional(Ns::DATA, 'advanceData', AdvanceData::fromXml(...)),
            productCodes: $reader->optional(Ns::DATA, 'productCodes', ProductCodes::fromXml(...)),
            lineExpressionIndicator: $reader->one(Ns::DATA, 'lineExpressionIndicator', Value::bool(...)),
            lineNatureIndicator: $reader->optional(Ns::DATA, 'lineNatureIndicator', static fn (DOMElement $e): LineNatureIndicator => LineNatureIndicator::from(Value::string($e))),
            lineDescription: $reader->optional(Ns::DATA, 'lineDescription', Value::string(...)),
            quantity: $reader->optional(Ns::DATA, 'quantity', Value::decimal(...)),
            unitOfMeasure: $reader->optional(Ns::DATA, 'unitOfMeasure', static fn (DOMElement $e): UnitOfMeasure => UnitOfMeasure::from(Value::string($e))),
            unitOfMeasureOwn: $reader->optional(Ns::DATA, 'unitOfMeasureOwn', Value::string(...)),
            unitPrice: $reader->optional(Ns::DATA, 'unitPrice', Value::decimal(...)),
            unitPriceHUF: $reader->optional(Ns::DATA, 'unitPriceHUF', Value::decimal(...)),
            lineDiscountData: $reader->optional(Ns::DATA, 'lineDiscountData', DiscountData::fromXml(...)),
            lineAmountsNormal: $reader->optional(Ns::DATA, 'lineAmountsNormal', LineAmountsNormal::fromXml(...)),
            lineAmountsSimplified: $reader->optional(Ns::DATA, 'lineAmountsSimplified', LineAmountsSimplified::fromXml(...)),
            intermediatedService: $reader->optional(Ns::DATA, 'intermediatedService', Value::bool(...)),
            aggregateInvoiceLineData: $reader->optional(Ns::DATA, 'aggregateInvoiceLineData', AggregateInvoiceLineData::fromXml(...)),
            newTransportMean: $reader->optional(Ns::DATA, 'newTransportMean', NewTransportMean::fromXml(...)),
            depositIndicator: $reader->optional(Ns::DATA, 'depositIndicator', Value::bool(...)),
            obligatedForProductFee: $reader->optional(Ns::DATA, 'obligatedForProductFee', Value::bool(...)),
            GPCExcise: $reader->optional(Ns::DATA, 'GPCExcise', Value::decimal(...)),
            dieselOilPurchase: $reader->optional(Ns::DATA, 'dieselOilPurchase', DieselOilPurchase::fromXml(...)),
            netaDeclaration: $reader->optional(Ns::DATA, 'netaDeclaration', Value::bool(...)),
            productFeeClause: $reader->optional(Ns::DATA, 'productFeeClause', ProductFeeClause::fromXml(...)),
            lineProductFeeContent: $reader->many(Ns::DATA, 'lineProductFeeContent', ProductFeeData::fromXml(...)),
            conventionalLineInfo: $reader->optional(Ns::DATA, 'conventionalLineInfo', ConventionalInvoiceInfo::fromXml(...)),
            additionalLineData: $reader->many(Ns::DATA, 'additionalLineData', AdditionalData::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'lineNumber', (string) $this->lineNumber);
        $writer->child(Ns::DATA, 'lineModificationReference', $this->lineModificationReference);
        $writer->child(Ns::DATA, 'referencesToOtherLines', $this->referencesToOtherLines);
        $writer->child(Ns::DATA, 'advanceData', $this->advanceData);
        $writer->child(Ns::DATA, 'productCodes', $this->productCodes);
        $writer->text(Ns::DATA, 'lineExpressionIndicator', Value::fromBool($this->lineExpressionIndicator));
        $writer->text(Ns::DATA, 'lineNatureIndicator', $this->lineNatureIndicator?->value);
        $writer->text(Ns::DATA, 'lineDescription', $this->lineDescription);
        $writer->text(Ns::DATA, 'quantity', $this->quantity?->value);
        $writer->text(Ns::DATA, 'unitOfMeasure', $this->unitOfMeasure?->value);
        $writer->text(Ns::DATA, 'unitOfMeasureOwn', $this->unitOfMeasureOwn);
        $writer->text(Ns::DATA, 'unitPrice', $this->unitPrice?->value);
        $writer->text(Ns::DATA, 'unitPriceHUF', $this->unitPriceHUF?->value);
        $writer->child(Ns::DATA, 'lineDiscountData', $this->lineDiscountData);
        $writer->child(Ns::DATA, 'lineAmountsNormal', $this->lineAmountsNormal);
        $writer->child(Ns::DATA, 'lineAmountsSimplified', $this->lineAmountsSimplified);
        $writer->text(Ns::DATA, 'intermediatedService', Value::fromBool($this->intermediatedService));
        $writer->child(Ns::DATA, 'aggregateInvoiceLineData', $this->aggregateInvoiceLineData);
        $writer->child(Ns::DATA, 'newTransportMean', $this->newTransportMean);
        $writer->text(Ns::DATA, 'depositIndicator', Value::fromBool($this->depositIndicator));
        $writer->text(Ns::DATA, 'obligatedForProductFee', Value::fromBool($this->obligatedForProductFee));
        $writer->text(Ns::DATA, 'GPCExcise', $this->GPCExcise?->value);
        $writer->child(Ns::DATA, 'dieselOilPurchase', $this->dieselOilPurchase);
        $writer->text(Ns::DATA, 'netaDeclaration', Value::fromBool($this->netaDeclaration));
        $writer->child(Ns::DATA, 'productFeeClause', $this->productFeeClause);
        $writer->children(Ns::DATA, 'lineProductFeeContent', $this->lineProductFeeContent);
        $writer->child(Ns::DATA, 'conventionalLineInfo', $this->conventionalLineInfo);
        $writer->children(Ns::DATA, 'additionalLineData', $this->additionalLineData);
    }
}
