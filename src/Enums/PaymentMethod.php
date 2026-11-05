<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Enums;

/**
 * Payment method.
 */
enum PaymentMethod: string
{
    case Transfer = 'TRANSFER';
    case Cash = 'CASH';
    case Card = 'CARD';
    case Voucher = 'VOUCHER';
    case Other = 'OTHER';
}
