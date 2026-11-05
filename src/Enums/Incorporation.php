<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

/**
 * Legal form of a taxpayer.
 */
enum Incorporation: string
{
    case Organization = 'ORGANIZATION';
    case SelfEmployed = 'SELF_EMPLOYED';
    case TaxablePerson = 'TAXABLE_PERSON';
}
