<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Tests\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;

/**
 * Exclusive C14N of a document after removing what carries no data: comments,
 * whitespace-only text nodes between elements and xsi:schemaLocation hints.
 */
final class CanonicalXml
{
    public static function of(string $xml): string
    {
        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Invalid XML');
        }

        $xpath = new DOMXPath($document);
        $remove = [];
        foreach ($xpath->query('//comment() | //text()[normalize-space(.) = ""]') ?: [] as $node) {
            $remove[] = $node;
        }
        foreach ($remove as $node) {
            /** @var DOMNode $node */
            $node->parentNode?->removeChild($node);
        }

        foreach ($xpath->query('//*') ?: [] as $element) {
            if ($element instanceof DOMElement) {
                $element->removeAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'schemaLocation');
            }
        }

        return (string) $document->C14N(true, false);
    }
}
