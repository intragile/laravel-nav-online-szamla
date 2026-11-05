<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Exceptions;

/**
 * Invoice data cannot be mapped to or from the typed model, for example because a
 * required element is missing or a choice has more than one member set.
 */
final class InvoiceModelException extends NavException {}
