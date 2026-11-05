<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Exceptions;

use LibXMLError;

/**
 * The XML document does not conform to the official NAV schema.
 */
final class XsdValidationException extends NavException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly string $schema, public readonly array $errors)
    {
        parent::__construct(sprintf("XML does not conform to %s:\n- %s", $schema, implode("\n- ", $errors)));
    }

    /**
     * @param  array<LibXMLError>  $errors
     */
    public static function fromLibxml(string $schema, array $errors): self
    {
        return new self($schema, array_values(array_map(
            static fn (LibXMLError $e): string => sprintf('line %d: %s', $e->line, trim($e->message)),
            $errors,
        )));
    }
}
