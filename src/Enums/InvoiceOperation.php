<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

/**
 * Operation of an invoice data submission.
 */
enum InvoiceOperation: string
{
    case Create = 'CREATE';
    case Modify = 'MODIFY';
    case Storno = 'STORNO';
}
