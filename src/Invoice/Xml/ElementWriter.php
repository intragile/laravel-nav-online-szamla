<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Xml;

use DOMDocument;
use DOMElement;
use IntrAgile\NavOnlineSzamla\Xml\Ns;

/**
 * Appends child elements to one complex type instance. Null values are skipped.
 */
final class ElementWriter
{
    private const PREFIXES = [
        Ns::DATA => '',
        Ns::BASE => 'base',
        Ns::COMMON => 'common',
    ];

    public function __construct(private readonly DOMDocument $document, private readonly DOMElement $element) {}

    public function text(string $ns, string $name, ?string $value): void
    {
        if ($value !== null) {
            $this->append($ns, $name)->appendChild($this->document->createTextNode($value));
        }
    }

    /**
     * @param  list<string>  $values
     */
    public function texts(string $ns, string $name, array $values): void
    {
        foreach ($values as $value) {
            $this->text($ns, $name, $value);
        }
    }

    public function child(string $ns, string $name, ?ModelElement $value): void
    {
        if ($value !== null) {
            $value->writeXml(new self($this->document, $this->append($ns, $name)));
        }
    }

    /**
     * @param  list<ModelElement>  $values
     */
    public function children(string $ns, string $name, array $values): void
    {
        foreach ($values as $value) {
            $this->child($ns, $name, $value);
        }
    }

    /**
     * Serializes a model as a standalone document with the conventional NAV prefixes.
     */
    public static function document(string $ns, string $name, ModelElement $model): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = $document->createElementNS($ns, self::qualify($ns, $name));
        foreach (self::PREFIXES as $uri => $prefix) {
            $root->setAttributeNS('http://www.w3.org/2000/xmlns/', $prefix === '' ? 'xmlns' : 'xmlns:'.$prefix, $uri);
        }
        $document->appendChild($root);

        $model->writeXml(new self($document, $root));

        return (string) $document->saveXML();
    }

    private function append(string $ns, string $name): DOMElement
    {
        $child = $this->document->createElementNS($ns, self::qualify($ns, $name));
        $this->element->appendChild($child);

        return $child;
    }

    private static function qualify(string $ns, string $name): string
    {
        $prefix = self::PREFIXES[$ns] ?? '';

        return $prefix === '' ? $name : $prefix.':'.$name;
    }
}
