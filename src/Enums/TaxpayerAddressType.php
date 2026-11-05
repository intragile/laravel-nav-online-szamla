<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

/**
 * Type of a registered taxpayer address.
 */
enum TaxpayerAddressType: string
{
    case Hq = 'HQ';
    case Site = 'SITE';
    case Branch = 'BRANCH';
}
