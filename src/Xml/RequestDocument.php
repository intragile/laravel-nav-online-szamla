<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Xml;

use DateTimeInterface;
use DOMDocument;
use DOMElement;
use IntrAgile\NavOnlineSzamla\Config\Credentials;
use IntrAgile\NavOnlineSzamla\Config\Software;
use IntrAgile\NavOnlineSzamla\Crypto\Signer;
use IntrAgile\NavOnlineSzamla\Support\Timestamp;

/**
 * Builds an API request: header, user and software blocks, followed by the
 * operation-specific elements.
 *
 * Elements must be added in the order defined by the invoiceApi.xsd sequences.
 */
final class RequestDocument
{
    private DOMDocument $document;

    private DOMElement $root;

    /**
     * @param  list<array{operation: string, data: string}>  $signedOperations
     */
    public function __construct(
        string $rootElement,
        Credentials $credentials,
        Software $software,
        string $requestId,
        DateTimeInterface $timestamp,
        array $signedOperations = [],
    ) {
        $this->document = new DOMDocument('1.0', 'UTF-8');
        $this->root = $this->document->createElementNS(Ns::API, $rootElement);
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:common', Ns::COMMON);
        $this->document->appendChild($this->root);

        $header = $this->common($this->root, 'header');
        $this->common($header, 'requestId', $requestId);
        $this->common($header, 'timestamp', Timestamp::header($timestamp));
        $this->common($header, 'requestVersion', '3.0');
        $this->common($header, 'headerVersion', '1.0');

        $user = $this->common($this->root, 'user');
        $this->common($user, 'login', $credentials->login);
        $this->common($user, 'passwordHash', Signer::passwordHash($credentials->password))->setAttribute('cryptoType', 'SHA-512');
        $this->common($user, 'taxNumber', $credentials->taxNumber);
        $this->common($user, 'requestSignature', Signer::requestSignature($requestId, $timestamp, $credentials->signKey, $signedOperations))
            ->setAttribute('cryptoType', 'SHA3-512');

        $sw = $this->add($this->root, 'software');
        $this->add($sw, 'softwareId', $software->id);
        $this->add($sw, 'softwareName', $software->name);
        $this->add($sw, 'softwareOperation', $software->operation);
        $this->add($sw, 'softwareMainVersion', $software->mainVersion);
        $this->add($sw, 'softwareDevName', $software->devName);
        $this->add($sw, 'softwareDevContact', $software->devContact);
        $this->addOptional($sw, 'softwareDevCountryCode', $software->devCountryCode);
        $this->addOptional($sw, 'softwareDevTaxNumber', $software->devTaxNumber);
    }

    public function root(): DOMElement
    {
        return $this->root;
    }

    public function add(DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $element = $this->document->createElementNS(Ns::API, $name);
        if ($value !== null) {
            $element->appendChild($this->document->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }

    public function addOptional(DOMElement $parent, string $name, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->add($parent, $name, $value);
        }
    }

    public function toXml(): string
    {
        return (string) $this->document->saveXML();
    }

    private function common(DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $element = $this->document->createElementNS(Ns::COMMON, 'common:'.$name);
        if ($value !== null) {
            $element->appendChild($this->document->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }
}
