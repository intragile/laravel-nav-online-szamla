<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Invoice\Xml;

use DOMDocument;
use DOMElement;
use IntrAgile\NavOnlineSzamla\Exceptions\InvoiceModelException;

final class DocumentReader
{
    /**
     * Parses an XML string and returns its root element after checking its name.
     */
    public static function root(string $xml, string $ns, string $name): DOMElement
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;
            // LIBXML_NONET prevents network access while loading (XXE hardening).
            if (! $document->loadXML($xml, LIBXML_NONET)) {
                $error = libxml_get_last_error();
                throw new InvoiceModelException('Invalid XML: '.($error !== false ? trim($error->message) : 'unknown error'));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->documentElement;
        if ($root === null || $root->namespaceURI !== $ns || $root->localName !== $name) {
            throw new InvoiceModelException(sprintf('Expected root element {%s}%s.', $ns, $name));
        }

        return $root;
    }
}
