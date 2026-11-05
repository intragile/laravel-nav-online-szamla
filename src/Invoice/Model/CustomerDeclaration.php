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
 * Should the supplier, based on statement given by the purchaser, be exempted from paying product fee, then the product stream affected.
 *
 * Generated from CustomerDeclarationType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class CustomerDeclaration implements ModelElement
{
    /**
     * @param  ProductStream  $productStream  Product stream.
     * @param  Decimal|null  $productFeeWeight  Weight of product fee obliged product in kilogram.
     */
    public function __construct(
        public ProductStream $productStream,
        public ?Decimal $productFeeWeight = null,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            productStream: $reader->one(Ns::DATA, 'productStream', static fn (DOMElement $e): ProductStream => ProductStream::from(Value::string($e))),
            productFeeWeight: $reader->optional(Ns::DATA, 'productFeeWeight', Value::decimal(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'productStream', $this->productStream->value);
        $writer->text(Ns::DATA, 'productFeeWeight', $this->productFeeWeight?->value);
    }
}
