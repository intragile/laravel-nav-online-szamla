<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Exceptions;

use IntrAgile\NavOnlineSzamla\Invoice\Validation\Violation;

/**
 * The invoice data would be rejected by a blocking NAV business validation.
 */
final class InvoiceValidationException extends NavException
{
    /**
     * @param  non-empty-list<Violation>  $violations
     */
    public function __construct(public readonly array $violations)
    {
        parent::__construct("Invoice data fails NAV business validation:\n- ".implode("\n- ", array_map(
            static fn (Violation $v): string => sprintf('%s at %s: %s', $v->code, $v->path, $v->message),
            $violations,
        )));
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_values(array_unique(array_map(static fn (Violation $v): string => $v->code, $this->violations)));
    }
}
