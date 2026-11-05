<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

/**
 * Direction of an invoice from the queried taxpayer's point of view.
 */
enum InvoiceDirection: string
{
    case Inbound = 'INBOUND';
    case Outbound = 'OUTBOUND';
}
