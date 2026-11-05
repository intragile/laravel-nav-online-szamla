<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Field type including the different product and service codes.
 *
 * Generated from ProductCodeType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class ProductCode implements ModelElement
{
    /**
     * @param  ProductCodeCategory  $productCodeCategory  The kind of product code (f. ex. VTSZ, CsK, etc.).
     * @param  string|null  $productCodeValue  The value of (not own) product code.
     * @param  string|null  $productCodeOwnValue  Own product code value.
     */
    public function __construct(
        public ProductCodeCategory $productCodeCategory,
        public ?string $productCodeValue = null,
        public ?string $productCodeOwnValue = null,
    ) {
        if ((($this->productCodeValue !== null ? 1 : 0) + ($this->productCodeOwnValue !== null ? 1 : 0)) !== 1) {
            throw new InvoiceModelException('ProductCode: Exactly one of productCodeValue, productCodeOwnValue must be set.');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            productCodeCategory: $reader->one(Ns::DATA, 'productCodeCategory', static fn (DOMElement $e): ProductCodeCategory => ProductCodeCategory::from(Value::string($e))),
            productCodeValue: $reader->optional(Ns::DATA, 'productCodeValue', Value::string(...)),
            productCodeOwnValue: $reader->optional(Ns::DATA, 'productCodeOwnValue', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'productCodeCategory', $this->productCodeCategory->value);
        $writer->text(Ns::DATA, 'productCodeValue', $this->productCodeValue);
        $writer->text(Ns::DATA, 'productCodeOwnValue', $this->productCodeOwnValue);
    }
}
