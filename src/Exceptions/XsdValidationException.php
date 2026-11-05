<?php

declare(strict_types=1);

namespace Intragile\NavOnlineSzamla\Exceptions;

use LibXMLError;

/** A kimenő XML nem felel meg a hivatalos NAV sémának. Beküldés előtt dobódik. */
final class XsdValidationException extends NavException
{
    /** @param  list<string>  $errors */
    public function __construct(public readonly string $schema, public readonly array $errors)
    {
        parent::__construct(sprintf("Az XML nem felel meg a(z) %s sémának:\n- %s", $schema, implode("\n- ", $errors)));
    }

    /** @param  array<LibXMLError>  $errors */
    public static function fromLibxml(string $schema, array $errors): self
    {
        return new self($schema, array_values(array_map(
            static fn (LibXMLError $e): string => sprintf('%d. sor: %s', $e->line, trim($e->message)),
            $errors,
        )));
    }
}
