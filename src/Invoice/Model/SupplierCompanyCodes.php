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
 * Company codes of the supplier.
 *
 * Generated from SupplierCompanyCodesType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class SupplierCompanyCodes implements ModelElement
{
    /**
     * @param  list<string>  $supplierCompanyCode  Company code of the supplier.
     */
    public function __construct(
        public array $supplierCompanyCode,
    ) {
        if (count($this->supplierCompanyCode) < 1) {
            throw new InvoiceModelException('SupplierCompanyCodes: supplierCompanyCode requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            supplierCompanyCode: $reader->many(Ns::DATA, 'supplierCompanyCode', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->texts(Ns::DATA, 'supplierCompanyCode', $this->supplierCompanyCode);
    }
}
