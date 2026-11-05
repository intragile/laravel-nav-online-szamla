<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Model;

use DateTimeImmutable;
use DOMElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementReader;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ElementWriter;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\ModelElement;
use IntrAgile\NavOnlineSzamla\Invoice\Xml\Value;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Data of the document verifying the declaration submitted on the product fee according to the Paragraph (3), Section 13 and the Paragraph (3) Section 25 of the Act LXXXV of 2011.
 *
 * Generated from PaymentEvidenceDocumentDataType (invoiceData.xsd) by bin/generate-model.php. Do not edit.
 */
final readonly class PaymentEvidenceDocumentData implements ModelElement
{
    /**
     * @param  string  $evidenceDocumentNo  Sequential number of the invoice, or other document considered as such.
     * @param  DateTimeImmutable  $evidenceDocumentDate  Date of issue of the invoice.
     * @param  string  $obligatedName  Name of obligator.
     * @param  Address  $obligatedAddress  Address of obligator.
     * @param  TaxNumber  $obligatedTaxNumber  Tax number of obligated.
     */
    public function __construct(
        public string $evidenceDocumentNo,
        public DateTimeImmutable $evidenceDocumentDate,
        public string $obligatedName,
        public Address $obligatedAddress,
        public TaxNumber $obligatedTaxNumber,
    ) {}

    public static function fromXml(DOMElement $element): self
    {
        $reader = new ElementReader($element);
        $model = new self(
            evidenceDocumentNo: $reader->one(Ns::DATA, 'evidenceDocumentNo', Value::string(...)),
            evidenceDocumentDate: $reader->one(Ns::DATA, 'evidenceDocumentDate', Value::date(...)),
            obligatedName: $reader->one(Ns::DATA, 'obligatedName', Value::string(...)),
            obligatedAddress: $reader->one(Ns::DATA, 'obligatedAddress', Address::fromXml(...)),
            obligatedTaxNumber: $reader->one(Ns::DATA, 'obligatedTaxNumber', TaxNumber::fromXml(...)),
        );
        $reader->assertConsumed();

        return $model;
    }

    public function writeXml(ElementWriter $writer): void
    {
        $writer->text(Ns::DATA, 'evidenceDocumentNo', $this->evidenceDocumentNo);
        $writer->text(Ns::DATA, 'evidenceDocumentDate', Value::fromDate($this->evidenceDocumentDate));
        $writer->text(Ns::DATA, 'obligatedName', $this->obligatedName);
        $writer->child(Ns::DATA, 'obligatedAddress', $this->obligatedAddress);
        $writer->child(Ns::DATA, 'obligatedTaxNumber', $this->obligatedTaxNumber);
    }
}
