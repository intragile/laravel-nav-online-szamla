<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Requests;

use DateTimeImmutable;
use DateTimeInterface;
use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\NavOnlineSzamla\Enums\AnnulmentCode;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
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

    /**
     * Restore an annulment from the XML produced by toXml().
     */
    public static function fromXml(string $xml): self
    {
        $document = new DOMDocument;
        if (! @$document->loadXML($xml, LIBXML_NONET)) {
            throw new NavException('Invalid InvoiceAnnulment XML.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('a', Ns::ANNUL);
        $value = static function (string $name) use ($xpath): string {
            $nodes = $xpath->query('/a:InvoiceAnnulment/a:'.$name);
            $node = $nodes === false ? null : $nodes->item(0);
            if (! $node instanceof DOMElement) {
                throw new NavException("InvoiceAnnulment XML lacks {$name}.");
            }

            return $node->textContent;
        };

        return new self(
            $value('annulmentReference'),
            AnnulmentCode::from($value('annulmentCode')),
            $value('annulmentReason'),
            new DateTimeImmutable($value('annulmentTimestamp')),
        );
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
