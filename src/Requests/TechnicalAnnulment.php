<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Requests;

use DateTimeImmutable;
use DateTimeInterface;
use DOMDocument;
use IntrAgile\NavOnlineSzamla\Enums\AnnulmentCode;
use IntrAgile\NavOnlineSzamla\Support\Timestamp;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Technical annulment of a previously submitted invoice (manageAnnulment).
 *
 * A technical annulment only takes effect after it is approved on the Online Számla web
 * interface; track it with queryTransactionStatus.
 */
final readonly class TechnicalAnnulment
{
    public DateTimeImmutable $timestamp;

    public function __construct(
        public string $invoiceNumber,
        public AnnulmentCode $code,
        public string $reason,
        ?DateTimeInterface $timestamp = null,
    ) {
        $this->timestamp = $timestamp === null ? Timestamp::now() : Timestamp::utc($timestamp);
    }

    public function toXml(): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS(Ns::ANNUL, 'InvoiceAnnulment');
        $document->appendChild($root);

        foreach ([
            'annulmentReference' => $this->invoiceNumber,
            'annulmentTimestamp' => Timestamp::header($this->timestamp),
            'annulmentCode' => $this->code->value,
            'annulmentReason' => $this->reason,
        ] as $name => $value) {
            $element = $document->createElementNS(Ns::ANNUL, $name);
            $element->appendChild($document->createTextNode($value));
            $root->appendChild($element);
        }

        return (string) $document->saveXML();
    }
}
