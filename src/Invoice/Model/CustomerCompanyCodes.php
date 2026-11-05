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
 * Company codes of the customer.
 *
 * Generated from CustomerCompanyCodesType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class CustomerCompanyCodes implements ModelElement
{
    /**
     * @param  list<string>  $customerCompanyCode  Company code of the customer.
     */
    public function __construct(
        public array $customerCompanyCode,
    ) {
        if (count($this->customerCompanyCode) < 1) {
            throw new InvoiceModelException('CustomerCompanyCodes: customerCompanyCode requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            customerCompanyCode: $reader->many(Ns::DATA, 'customerCompanyCode', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->texts(Ns::DATA, 'customerCompanyCode', $this->customerCompanyCode);
    }
}
