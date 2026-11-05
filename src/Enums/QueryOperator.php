<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

/**
 * Relational operator of a digest query parameter.
 */
enum QueryOperator: string
{
    case Eq = 'EQ';
    case Gt = 'GT';
    case Gte = 'GTE';
    case Lt = 'LT';
    case Lte = 'LTE';
}
