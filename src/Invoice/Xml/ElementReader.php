<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Xml;

use DOMElement;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;

/**
 * Reads the child elements of one complex type instance.
 *
 * Every child must be consumed; an element the model does not know about is an error
 * rather than silently dropped data.
 */
final class ElementReader
{
    /** @var array<string, list<DOMElement>> */
    private array $children = [];

    public function __construct(private readonly DOMElement $element)
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $this->children[self::key($child->namespaceURI, $child->localName ?? $child->nodeName)][] = $child;
            }
        }
    }

    /**
     * @template T
     *
     * @param  callable(DOMElement): T  $convert
     * @return T
     */
    public function one(string $ns, string $name, callable $convert): mixed
    {
        return $this->optional($ns, $name, $convert)
            ?? throw new InvoiceModelException(sprintf('Element <%s> requires child <%s>.', $this->element->localName, $name));
    }

    /**
     * @template T
     *
     * @param  callable(DOMElement): T  $convert
     * @return T|null
     */
    public function optional(string $ns, string $name, callable $convert): mixed
    {
        $elements = $this->take($ns, $name);
        if (count($elements) > 1) {
            throw new InvoiceModelException(sprintf('Element <%s> allows one <%s>, got %d.', $this->element->localName, $name, count($elements)));
        }

        return $elements === [] ? null : $convert($elements[0]);
    }

    /**
     * @template T
     *
     * @param  callable(DOMElement): T  $convert
     * @return list<T>
     */
    public function many(string $ns, string $name, callable $convert): array
    {
        return array_map($convert, $this->take($ns, $name));
    }

    /**
     * @throws InvoiceModelException when a child element was not consumed
     */
    public function assertConsumed(): void
    {
        foreach ($this->children as $key => $elements) {
            if ($elements !== []) {
                throw new InvoiceModelException(sprintf('Element <%s> has unexpected child %s.', $this->element->localName, $key));
            }
        }
    }

    /**
     * @return list<DOMElement>
     */
    private function take(string $ns, string $name): array
    {
        $key = self::key($ns, $name);
        $elements = $this->children[$key] ?? [];
        $this->children[$key] = [];

        return $elements;
    }

    private static function key(?string $ns, string $name): string
    {
        return '{'.$ns.'}'.$name;
    }
}
