<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Xml;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
use IntrAgile\NavOnlineSzamla\Exceptions\TechnicalValidationMessage;

/**
 * XPath reader for NAV responses, with the prefixes api, common and base registered.
 *
 * Queries match on namespace URIs, never on the prefixes used in the response.
 */
final class ResponseDocument
{
    private DOMXPath $xpath;

    private function __construct(private readonly DOMDocument $document, public readonly string $raw)
    {
        $this->xpath = new DOMXPath($document);
        $this->xpath->registerNamespace('api', Ns::API);
        $this->xpath->registerNamespace('common', Ns::COMMON);
        $this->xpath->registerNamespace('base', Ns::BASE);
    }

    public static function tryParse(string $xml): ?self
    {
        if (trim($xml) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            // LIBXML_NONET prevents network access while parsing (XXE hardening).
            if (! $document->loadXML($xml, LIBXML_NONET) || $document->documentElement === null) {
                return null;
            }

            return new self($document, $xml);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function rootName(): string
    {
        return (string) $this->document->documentElement?->localName;
    }

    public function string(string $path, ?DOMNode $context = null): ?string
    {
        $nodes = $this->xpath->query($path, $context);
        $node = $nodes === false ? null : $nodes->item(0);

        return $node instanceof DOMNode ? $node->textContent : null;
    }

    public function requireString(string $path, ?DOMNode $context = null): string
    {
        return $this->string($path, $context)
            ?? throw new NavException("Missing element in NAV response: {$path}");
    }

    /** @return list<DOMElement> */
    public function elements(string $path, ?DOMNode $context = null): array
    {
        $result = [];
        foreach ($this->xpath->query($path, $context) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $result[] = $node;
            }
        }

        return $result;
    }

    public function element(string $path, ?DOMNode $context = null): ?DOMElement
    {
        return $this->elements($path, $context)[0] ?? null;
    }

    public function int(string $path, ?DOMNode $context = null): ?int
    {
        $value = $this->string($path, $context);

        return $value === null ? null : (int) $value;
    }

    public function bool(string $path, ?DOMNode $context = null): ?bool
    {
        $value = $this->string($path, $context);

        return $value === null ? null : $value === 'true';
    }

    public function date(string $path, ?DOMNode $context = null): ?\DateTimeImmutable
    {
        $value = $this->string($path, $context);

        return $value === null ? null : new \DateTimeImmutable($value);
    }

    public function funcCode(): ?string
    {
        return $this->string('/*/common:result/common:funcCode');
    }

    public function errorCode(): ?string
    {
        return $this->string('/*/common:result/common:errorCode');
    }

    public function message(): ?string
    {
        return $this->string('/*/common:result/common:message');
    }

    /** @return list<TechnicalValidationMessage> */
    public function technicalValidationMessages(): array
    {
        return array_map(
            fn (DOMElement $e): TechnicalValidationMessage => new TechnicalValidationMessage(
                $this->string('*[local-name()="validationResultCode"]', $e) ?? 'ERROR',
                $this->string('*[local-name()="validationErrorCode"]', $e),
                $this->string('*[local-name()="message"]', $e),
            ),
            $this->elements('/*/*[local-name()="technicalValidationMessages"]'),
        );
    }

    public function document(): DOMDocument
    {
        return $this->document;
    }
}
