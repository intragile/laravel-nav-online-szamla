<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Product codes.
 *
 * Generated from ProductCodesType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class ProductCodes implements ModelElement
{
    /**
     * @param  list<ProductCode>  $productCode  Product code.
     */
    public function __construct(
        public array $productCode,
    ) {
        if (count($this->productCode) < 1) {
            throw new InvoiceModelException('ProductCodes: productCode requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            productCode: $reader->many(Ns::DATA, 'productCode', ProductCode::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->children(Ns::DATA, 'productCode', $this->productCode);
    }
}
