<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DateTimeImmutable;
use DOMElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\DocumentReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Invoice exchange data.
 *
 * Generated from InvoiceDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class InvoiceData implements ModelElement
{
    /**
     * @param  string  $invoiceNumber  Sequential number of the original invoice or modification document - section 169 (b) or section 170 (1) b) of the VAT law.
     * @param  DateTimeImmutable  $invoiceIssueDate  Date of issue of the invoice or the modification document - section 169 (a) of the VAT law, section 170 (1) a) of the VAT law.
     * @param  bool  $completenessIndicator  Indicates whether the data exchange is identical with the invoice (the invoice does not contain any more data).
     * @param  InvoiceMain  $invoiceMain  A common type to describe invoice information.
     */
    public function __construct(
        public string $invoiceNumber,
        public DateTimeImmutable $invoiceIssueDate,
        public bool $completenessIndicator,
        public InvoiceMain $invoiceMain,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            invoiceNumber: $reader->one(Ns::DATA, 'invoiceNumber', Value::string(...)),
            invoiceIssueDate: $reader->one(Ns::DATA, 'invoiceIssueDate', Value::date(...)),
            completenessIndicator: $reader->one(Ns::DATA, 'completenessIndicator', Value::bool(...)),
            invoiceMain: $reader->one(Ns::DATA, 'invoiceMain', InvoiceMain::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    /**
     * Parses a complete InvoiceData document.
     */
    public static function fromXmlString(string $xml): self
    {
        return self::fromXml(DocumentReader::root($xml, Ns::DATA, 'InvoiceData'));
    }

    /**
     * Serializes this model as a complete InvoiceData document.
     */
    public function toXml(): string
    {
        return ElementWriter::document(Ns::DATA, 'InvoiceData', $this);
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'invoiceNumber', $this->invoiceNumber);
        $writer->text(Ns::DATA, 'invoiceIssueDate', Value::fromDate($this->invoiceIssueDate));
        $writer->text(Ns::DATA, 'completenessIndicator', Value::fromBool($this->completenessIndicator));
        $writer->child(Ns::DATA, 'invoiceMain', $this->invoiceMain);
    }
}
