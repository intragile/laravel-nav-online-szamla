<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Modification or cancellation reference data.
 *
 * Generated from InvoiceReferenceType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class InvoiceReference implements ModelElement
{
    /**
     * @param  string  $originalInvoiceNumber  Sequence number of the original invoice, on which the modification occurs - section 170 (1) c) of the VAT law.
     * @param  bool  $modifyWithoutMaster  Indicates whether the modification references to an original invoice which is not and will not be exchanged.
     * @param  int  $modificationIndex  The unique sequence number referring to the original invoice.
     */
    public function __construct(
        public string $originalInvoiceNumber,
        public bool $modifyWithoutMaster,
        public int $modificationIndex,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            originalInvoiceNumber: $reader->one(Ns::DATA, 'originalInvoiceNumber', Value::string(...)),
            modifyWithoutMaster: $reader->one(Ns::DATA, 'modifyWithoutMaster', Value::bool(...)),
            modificationIndex: $reader->one(Ns::DATA, 'modificationIndex', Value::int(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'originalInvoiceNumber', $this->originalInvoiceNumber);
        $writer->text(Ns::DATA, 'modifyWithoutMaster', Value::fromBool($this->modifyWithoutMaster));
        $writer->text(Ns::DATA, 'modificationIndex', (string) $this->modificationIndex);
    }
}
