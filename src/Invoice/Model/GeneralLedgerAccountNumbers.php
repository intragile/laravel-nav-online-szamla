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
 * General ledger account numbers.
 *
 * Generated from GeneralLedgerAccountNumbersType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class GeneralLedgerAccountNumbers implements ModelElement
{
    /**
     * @param  list<string>  $generalLedgerAccountNumber  General ledger account number.
     */
    public function __construct(
        public array $generalLedgerAccountNumber,
    ) {
        if (count($this->generalLedgerAccountNumber) < 1) {
            throw new InvoiceModelException('GeneralLedgerAccountNumbers: generalLedgerAccountNumber requires at least 1 item(s).');
        }
    }

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            generalLedgerAccountNumber: $reader->many(Ns::DATA, 'generalLedgerAccountNumber', Value::string(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->texts(Ns::DATA, 'generalLedgerAccountNumber', $this->generalLedgerAccountNumber);
    }
}
