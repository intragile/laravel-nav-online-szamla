<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

/**
 * Invoice category.
 */
enum InvoiceCategory: string
{
    case Normal = 'NORMAL';
    case Simplified = 'SIMPLIFIED';
    case Aggregate = 'AGGREGATE';
}
