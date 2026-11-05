<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

/**
 * Form in which the invoice was issued.
 */
enum InvoiceAppearance: string
{
    case Paper = 'PAPER';
    case Electronic = 'ELECTRONIC';
    case Edi = 'EDI';
    case Unknown = 'UNKNOWN';
}
