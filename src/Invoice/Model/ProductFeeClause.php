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
 * Clauses according to the Act LXXXV of 2011 on Environmental Protection Product Fee (related to the item).
 *
 * Generated from ProductFeeClauseType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class ProductFeeClause implements ModelElement
{
    /**
     * @param  ProductFeeTakeoverData|null  $productFeeTakeoverData  Data in connection with takeover of environmental protection product fee.
     * @param  CustomerDeclaration|null  $customerDeclaration  Should the supplier, based on statement given by the purchaser, be exempted from paying product fee, then the product stream affected.
     */
    public function __construct(
        public ?ProductFeeTakeoverData $productFeeTakeoverData = null,
        public ?CustomerDeclaration $customerDeclaration = null,
    ) {
        if ((($this->productFeeTakeoverData !== null ? 1 : 0) + ($this->customerDeclaration !== null ? 1 : 0)) !== 1) {
            throw new InvoiceModelException('ProductFeeClause: Exactly one of productFeeTakeoverData, customerDeclaration must be set.');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            productFeeTakeoverData: $reader->optional(Ns::DATA, 'productFeeTakeoverData', ProductFeeTakeoverData::fromXml(...)),
            customerDeclaration: $reader->optional(Ns::DATA, 'customerDeclaration', CustomerDeclaration::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->child(Ns::DATA, 'productFeeTakeoverData', $this->productFeeTakeoverData);
        $writer->child(Ns::DATA, 'customerDeclaration', $this->customerDeclaration);
    }
}
