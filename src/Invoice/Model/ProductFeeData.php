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
 * Product charges data.
 *
 * Generated from ProductFeeDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class ProductFeeData implements ModelElement
{
    /**
     * @param  ProductCode  $productFeeCode  Product charges code (Kt or Csk code).
     * @param  Decimal  $productFeeQuantity  Quantity of product, according to product charge.
     * @param  ProductFeeMeasuringUnit  $productFeeMeasuringUnit  Unit of the rate (kg or piece).
     * @param  Decimal  $productFeeRate  Product fee rate (HUF/unit).
     * @param  Decimal  $productFeeAmount  Amount in Hungarian forints of the product fee.
     */
    public function __construct(
        public ProductCode $productFeeCode,
        public Decimal $productFeeQuantity,
        public ProductFeeMeasuringUnit $productFeeMeasuringUnit,
        public Decimal $productFeeRate,
        public Decimal $productFeeAmount,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            productFeeCode: $reader->one(Ns::DATA, 'productFeeCode', ProductCode::fromXml(...)),
            productFeeQuantity: $reader->one(Ns::DATA, 'productFeeQuantity', Value::decimal(...)),
            productFeeMeasuringUnit: $reader->one(Ns::DATA, 'productFeeMeasuringUnit', static fn (DOMElement $e): ProductFeeMeasuringUnit => ProductFeeMeasuringUnit::from(Value::string($e))),
            productFeeRate: $reader->one(Ns::DATA, 'productFeeRate', Value::decimal(...)),
            productFeeAmount: $reader->one(Ns::DATA, 'productFeeAmount', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::DATA, 'productFeeCode', $this->productFeeCode);
        $writer->text(Ns::DATA, 'productFeeQuantity', $this->productFeeQuantity->value);
        $writer->text(Ns::DATA, 'productFeeMeasuringUnit', $this->productFeeMeasuringUnit->value);
        $writer->text(Ns::DATA, 'productFeeRate', $this->productFeeRate->value);
        $writer->text(Ns::DATA, 'productFeeAmount', $this->productFeeAmount->value);
    }
}
