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
 * Contract numbers.
 *
 * Generated from ContractNumbersType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class ContractNumbers implements ModelElement
{
    /**
     * @param  list<string>  $contractNumber  Contract number.
     */
    public function __construct(
        public array $contractNumber,
    ) {
        if (count($this->contractNumber) < 1) {
            throw new InvoiceModelException('ContractNumbers: contractNumber requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            contractNumber: $reader->many(Ns::DATA, 'contractNumber', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->texts(Ns::DATA, 'contractNumber', $this->contractNumber);
    }
}
