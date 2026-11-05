<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Xml;

use DOMDocument;
use IntrAgile\NavOnlineSzamla\Exceptions\XsdValidationException;

final class XsdValidator
{
    /**
     * @throws XsdValidationException
     */
    public static function validate(string $xml, Schema $schema): void
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new DOMDocument;
            // LIBXML_NONET prevents network access while loading or validating (XXE hardening).
            $loaded = $document->loadXML($xml, LIBXML_NONET);

            if (! $loaded || ! $document->schemaValidate($schema->path(), LIBXML_NONET)) {
                throw XsdValidationException::fromLibxml($schema->value, libxml_get_errors());
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
